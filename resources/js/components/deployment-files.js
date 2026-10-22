import { EditorView, basicSetup } from 'codemirror';
import { keymap } from '@codemirror/view';
import { defaultKeymap, history, historyKeymap } from '@codemirror/commands';
import { html } from '@codemirror/lang-html';
import { css } from '@codemirror/lang-css';
import { javascript } from '@codemirror/lang-javascript';
import { php } from '@codemirror/lang-php';
import { oneDark } from '@codemirror/theme-one-dark';

export function deploymentFilesPage(deploymentId, deploymentData) {
    return {
        deploymentId: deploymentId,
        deployment: deploymentData,
        treeLoading: true,
        fileLoading: false,
        saving: false,
        items: [],
        currentDir: '',
        rootDir: '',
        openTabs: [], // Array of { path, name, size, content, originalContent, isModified }
        activeFile: null, // Points to active tab item
        editorView: null,
        theme: localStorage.getItem('ide_theme') || 'light', // 'light' (default) | 'dark'
        saveStatus: '', // 'Saved', 'Unsaved changes', 'Saving...'
        isCreatingFile: false,
        newFileName: '',
        searchFileQuery: '',

        get isModified() {
            return this.activeFile ? !!this.activeFile.isModified : false;
        },

        init() {
            this.loadTree();
            window.addEventListener('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                    e.preventDefault();
                    if (this.activeFile && !this.saving) {
                        this.saveFile();
                    }
                }
            });
        },

        showToast(message, type = 'info') {
            if (window.toast) {
                if (type === 'error') {
                    window.toast.error(message);
                } else if (type === 'success') {
                    window.toast.success(message);
                } else if (type === 'warning') {
                    window.toast.warning(message);
                } else {
                    window.toast.info(message);
                }
            } else {
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: { message, type }
                }));
            }
        },

        async loadTree(path = '') {
            this.treeLoading = true;
            try {
                const url = `/api/deployments/${this.deploymentId}/files/tree` + (path ? `?path=${encodeURIComponent(path)}` : '');
                const res = await fetch(url, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.success) {
                    this.rootDir = data.data.root_dir;
                    this.currentDir = data.data.current_dir;

                    // Format top-level items with level and tree metadata
                    this.items = (data.data.items || []).map(item => ({
                        ...item,
                        level: 0,
                        expanded: false,
                        loading: false,
                        loaded: false,
                        children: []
                    }));

                    // If no active file and no open tabs, try to open index.html automatically if present
                    if (!this.activeFile && this.openTabs.length === 0) {
                        const indexFile = this.items.find(i => i.type === 'file' && (i.name === 'index.html' || i.name === 'index.php'));
                        if (indexFile) {
                            this.openFile(indexFile);
                        }
                    }
                } else {
                    this.showToast(data.message || 'Failed to list directory', 'error');
                }
            } catch (err) {
                this.showToast('Network error while connecting to server', 'error');
            } finally {
                this.treeLoading = false;
            }
        },

        // Toggle folder expansion / accordion drop-down
        async toggleFolder(folder) {
            if (folder.expanded) {
                folder.expanded = false;
                return;
            }

            // If not loaded yet, fetch children from SFTP
            if (!folder.loaded) {
                folder.loading = true;
                try {
                    const url = `/api/deployments/${this.deploymentId}/files/tree?path=${encodeURIComponent(folder.path)}`;
                    const res = await fetch(url, {
                        headers: { 'Accept': 'application/json' }
                    });
                    const data = await res.json();
                    if (data.success) {
                        const nextLevel = (folder.level || 0) + 1;
                        folder.children = (data.data.items || []).map(item => ({
                            ...item,
                            level: nextLevel,
                            expanded: false,
                            loading: false,
                            loaded: false,
                            children: []
                        }));
                        folder.loaded = true;
                        folder.expanded = true;
                    } else {
                        this.showToast(data.message || 'Failed to read directory', 'error');
                    }
                } catch (err) {
                    this.showToast('Failed to load folder', 'error');
                } finally {
                    folder.loading = false;
                }
            } else {
                folder.expanded = true;
            }
        },

        // Helper to get visible flat list for the tree view (or filtered results)
        get visibleTreeItems() {
            const query = (this.searchFileQuery || '').trim().toLowerCase();

            // When searching, find any matching files/folders across all loaded levels
            if (query) {
                const matched = [];
                const searchTraverse = (list) => {
                    for (const item of list) {
                        if (item.name.toLowerCase().includes(query)) {
                            matched.push(item);
                        }
                        if (item.children && item.children.length > 0) {
                            searchTraverse(item.children);
                        }
                    }
                };
                searchTraverse(this.items);
                return matched;
            }

            // Normal hierarchical view
            const result = [];
            const traverse = (list) => {
                for (const item of list) {
                    result.push(item);
                    if (item.type === 'directory' && item.expanded && item.children) {
                        traverse(item.children);
                    }
                }
            };
            traverse(this.items);
            return result;
        },

        selectItem(item) {
            if (item.type === 'directory') {
                this.toggleFolder(item);
            } else {
                this.openFile(item);
            }
        },

        // Switch or load file into tabs
        async openFile(file) {
            // Check if already open in tabs
            const existingTab = this.openTabs.find(t => t.path === file.path);
            if (existingTab) {
                this.switchToTab(existingTab);
                return;
            }

            // Sync current editor content to active tab before opening new one
            if (this.activeFile && this.editorView) {
                this.activeFile.content = this.editorView.state.doc.toString();
            }

            this.fileLoading = true;
            try {
                const res = await fetch(`/api/deployments/${this.deploymentId}/files/read?path=${encodeURIComponent(file.path)}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.success) {
                    const newTab = {
                        path: file.path,
                        name: file.name,
                        size: file.size ?? 0,
                        content: data.data.content,
                        originalContent: data.data.content,
                        isModified: false
                    };

                    this.openTabs.push(newTab);
                    this.activeFile = newTab;
                    this.mountCodeMirror(newTab.content, newTab.name);
                    this.saveStatus = 'Saved';
                } else {
                    this.showToast(data.message || 'Could not read file', 'error');
                }
            } catch (err) {
                this.showToast('Failed to fetch file content', 'error');
            } finally {
                this.fileLoading = false;
            }
        },

        switchToTab(tab) {
            if (this.activeFile?.path === tab.path) return;

            // Save current doc content to current active tab state before switching
            if (this.activeFile && this.editorView) {
                this.activeFile.content = this.editorView.state.doc.toString();
            }

            this.activeFile = tab;
            this.mountCodeMirror(tab.content, tab.name);
            this.saveStatus = tab.isModified ? 'Unsaved changes' : 'Saved';
        },

        closeTab(tab, event) {
            if (event) {
                event.stopPropagation();
            }

            if (tab.isModified) {
                const discard = confirm(`"${tab.name}" has unsaved changes. Discard and close?`);
                if (!discard) return;
            }

            const index = this.openTabs.findIndex(t => t.path === tab.path);
            if (index === -1) return;

            this.openTabs.splice(index, 1);

            // If we closed the active tab, switch to another tab or null
            if (this.activeFile?.path === tab.path) {
                if (this.openTabs.length > 0) {
                    const nextIndex = Math.max(0, index - 1);
                    this.switchToTab(this.openTabs[nextIndex]);
                } else {
                    this.activeFile = null;
                    if (this.editorView) {
                        this.editorView.destroy();
                        this.editorView = null;
                    }
                    const container = document.getElementById('codemirror-editor');
                    if (container) container.innerHTML = '';
                    this.saveStatus = '';
                }
            }
        },

        // Prompt / Create a new file
        startNewFile() {
            this.isCreatingFile = true;
            this.newFileName = '';
            this.$nextTick(() => {
                const input = document.getElementById('new-file-name-input');
                if (input) input.focus();
            });
        },

        cancelNewFile() {
            this.isCreatingFile = false;
            this.newFileName = '';
        },

        async submitNewFile() {
            const trimmedName = this.newFileName.trim();
            if (!trimmedName) {
                this.cancelNewFile();
                return;
            }

            // Target directory
            const dir = this.currentDir || this.rootDir;
            const targetPath = `${dir.replace(/\/$/, '')}/${trimmedName.replace(/^\//, '')}`;

            try {
                const res = await fetch(`/api/deployments/${this.deploymentId}/files/create`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    body: JSON.stringify({ path: targetPath })
                });

                const data = await res.json();
                if (data.success) {
                    this.showToast(`Created ${trimmedName}`, 'success');
                    this.cancelNewFile();
                    await this.loadTree(dir);
                    this.openFile({
                        name: trimmedName,
                        path: targetPath,
                        type: 'file',
                        size: 0
                    });
                } else {
                    this.showToast(data.message || 'Failed to create file', 'error');
                }
            } catch (err) {
                this.showToast('Network error while creating file', 'error');
            }
        },

        getLanguageExtension(filename) {
            const ext = filename.split('.').pop()?.toLowerCase();
            switch (ext) {
                case 'html':
                case 'htm':
                    return html();
                case 'css':
                    return css();
                case 'js':
                case 'json':
                    return javascript();
                case 'php':
                    return php();
                default:
                    return [];
            }
        },

        mountCodeMirror(content, filename) {
            const container = document.getElementById('codemirror-editor');
            if (!container) return;

            // Clear old editor
            container.innerHTML = '';
            if (this.editorView) {
                this.editorView.destroy();
            }

            const extensions = [
                basicSetup,
                history(),
                keymap.of([...defaultKeymap, ...historyKeymap]),
                this.getLanguageExtension(filename),
                EditorView.updateListener.of((update) => {
                    if (update.docChanged && this.activeFile) {
                        const newDoc = update.state.doc.toString();
                        this.activeFile.content = newDoc;
                        this.activeFile.isModified = (newDoc !== this.activeFile.originalContent);
                        this.saveStatus = this.activeFile.isModified ? 'Unsaved changes' : 'Saved';
                    }
                }),
                EditorView.theme({
                    '&': { height: '100%', fontSize: '13.5px', fontFamily: 'JetBrains Mono, Menlo, Monaco, Consolas, monospace' },
                    '.cm-scroller': {
                        overflow: 'auto',
                        scrollbarColor: this.theme === 'dark' ? '#3e4451 #1e1e1e' : '#cbd5e1 #f8fafc',
                        scrollbarWidth: 'thin'
                    },
                    '.cm-scroller::-webkit-scrollbar': {
                        width: '10px',
                        height: '10px',
                        backgroundColor: this.theme === 'dark' ? '#1e1e1e' : '#f8fafc'
                    },
                    '.cm-scroller::-webkit-scrollbar-track': {
                        backgroundColor: this.theme === 'dark' ? '#1e1e1e' : '#f8fafc'
                    },
                    '.cm-scroller::-webkit-scrollbar-thumb': {
                        backgroundColor: this.theme === 'dark' ? '#3e4451' : '#cbd5e1',
                        borderRadius: '4px',
                        border: `2px solid ${this.theme === 'dark' ? '#1e1e1e' : '#f8fafc'}`
                    },
                    '.cm-scroller::-webkit-scrollbar-thumb:hover': {
                        backgroundColor: this.theme === 'dark' ? '#5c6370' : '#94a3b8'
                    },
                    '.cm-scroller::-webkit-scrollbar-corner': {
                        backgroundColor: this.theme === 'dark' ? '#1e1e1e' : '#f8fafc'
                    },
                    '.cm-content': { padding: '12px 0' }
                })
            ];

            if (this.theme === 'dark') {
                extensions.push(oneDark);
            }

            this.editorView = new EditorView({
                doc: content,
                extensions,
                parent: container
            });
        },

        toggleTheme() {
            this.theme = this.theme === 'dark' ? 'light' : 'dark';
            try {
                localStorage.setItem('ide_theme', this.theme);
            } catch (e) { }

            if (this.editorView && this.activeFile) {
                const currentDoc = this.editorView.state.doc.toString();
                this.mountCodeMirror(currentDoc, this.activeFile.name);
            }
        },

        async saveFile() {
            if (!this.activeFile || !this.editorView || this.saving) return;

            this.saving = true;
            this.saveStatus = 'Saving';

            const content = this.editorView.state.doc.toString();

            try {
                const res = await fetch(`/api/deployments/${this.deploymentId}/files/write`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    body: JSON.stringify({
                        path: this.activeFile.path,
                        content: content
                    })
                });

                const data = await res.json();
                if (data.success) {
                    this.activeFile.content = content;
                    this.activeFile.originalContent = content;
                    this.activeFile.isModified = false;
                    this.activeFile.size = content.length;
                    this.saveStatus = 'Saved';
                    this.showToast('File saved successfully', 'success');
                } else {
                    this.saveStatus = 'Failed to save';
                    this.showToast(data.message || 'Failed to save file', 'error');
                }
            } catch (err) {
                this.saveStatus = 'Error';
                this.showToast('Network error while saving file', 'error');
            } finally {
                this.saving = false;
            }
        },

        formatSize(bytes) {
            if (!bytes || bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
        }
    };
}
