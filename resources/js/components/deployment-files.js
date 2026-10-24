import { EditorView } from '@codemirror/view';
import { lineNumbers, highlightActiveLineGutter, highlightSpecialChars, drawSelection, dropCursor, rectangularSelection, crosshairCursor, highlightActiveLine, keymap } from '@codemirror/view';
import { EditorState } from '@codemirror/state';
import { foldGutter, syntaxHighlighting, defaultHighlightStyle, foldKeymap, LRLanguage } from '@codemirror/language';
import { history, defaultKeymap, historyKeymap } from '@codemirror/commands';
import { SearchQuery, setSearchQuery, findNext, findPrevious, replaceNext, replaceAll, search, SearchCursor, RegExpCursor } from '@codemirror/search';
import { closeBrackets, closeBracketsKeymap, autocompletion, completionKeymap } from '@codemirror/autocomplete';
import { parser as htmlParser, configureNesting } from '@lezer/html';
import { htmlCompletionSource, autoCloseTags } from '@codemirror/lang-html';
import { css } from '@codemirror/lang-css';
import { javascript, javascriptLanguage } from '@codemirror/lang-javascript';
import { php } from '@codemirror/lang-php';
import { oneDark } from '@codemirror/theme-one-dark';

// HTML tags dictionary for fast autocompletion (whether typing bare 'script' or '<script')
const HTML_TAG_NAMES = [
    "a","abbr","address","area","article","aside","audio","b","base","bdi","bdo",
    "blockquote","body","br","button","canvas","caption","center","cite","code",
    "col","colgroup","data","datalist","dd","del","details","dfn","dialog","div",
    "dl","dt","em","embed","fieldset","figcaption","figure","footer","form",
    "h1","h2","h3","h4","h5","h6","head","header","hgroup","hr","html","i",
    "iframe","img","input","ins","kbd","label","legend","li","link","main","map",
    "mark","menu","meta","meter","nav","noscript","object","ol","optgroup","option",
    "output","p","param","picture","pre","progress","q","rp","rt","ruby","s","samp",
    "script","section","select","slot","small","source","span","strong","style",
    "sub","summary","sup","svg","table","tbody","td","template","textarea","tfoot",
    "th","thead","time","title","tr","track","u","ul","var","video","wbr"
];

const VOID_TAGS = new Set(["area","base","br","col","embed","hr","img","input","link","meta","param","source","track","wbr"]);

function smartHtmlCompletionSource(context) {
    // 1. Try CodeMirror's standard HTML completion (covers attributes like class, id, src, type, etc.)
    const standard = htmlCompletionSource(context);
    if (standard) return standard;

    // 2. Custom smart tag completion: handles typing bare tag name like 'script', 'div', or '<script'
    const word = context.matchBefore(/<?[a-zA-Z0-9]*/);
    if (!word || (word.from === word.to && !context.explicit)) return null;

    const hasBracket = word.text.startsWith('<');
    const prefix = (hasBracket ? word.text.slice(1) : word.text).toLowerCase();

    if (!prefix && !context.explicit) return null;

    const matchedTags = HTML_TAG_NAMES.filter(tag => tag.startsWith(prefix));
    if (!matchedTags.length) return null;

    return {
        from: word.from,
        options: matchedTags.map(tag => ({
            label: tag,
            type: 'type',
            detail: VOID_TAGS.has(tag) ? 'tag' : `<${tag}>...</${tag}>`,
            apply: (view, completion, from, to) => {
                let insert = '';
                let cursorOffset = 0;

                if (VOID_TAGS.has(tag)) {
                    insert = `<${tag} />`;
                    cursorOffset = insert.length;
                } else if (tag === 'script' || tag === 'style') {
                    insert = `<${tag}>\n\t\n</${tag}>`;
                    cursorOffset = tag.length + 3; // Position inside the body on the empty line
                } else {
                    insert = `<${tag}></${tag}>`;
                    cursorOffset = tag.length + 2; // Position between <tag> and </tag>
                }

                view.dispatch({
                    changes: { from, to, insert },
                    selection: { anchor: from + cursorOffset }
                });
            }
        }))
    };
}

// Fast HTML language with embedded JavaScript parsing and tag autocompletion
const htmlWithJsLanguage = LRLanguage.define({
    name: 'html',
    parser: htmlParser.configure({
        wrap: configureNesting([
            { tag: 'script', attrs: () => true, parser: javascriptLanguage.parser }
        ])
    }),
    languageData: {
        autocomplete: smartHtmlCompletionSource
    }
});

export function deploymentFilesPage(deploymentId, deploymentData) {
    let rawEditorView = null;
    let isCurrentlyModified = false;
    const tabContents = new Map(); // path -> string content (outside Alpine proxy)

    return {
        deploymentId: deploymentId,
        deployment: deploymentData,
        treeLoading: true,
        fileLoading: false,
        saving: false,
        downloadingZip: false,
        items: [],
        currentDir: '',
        rootDir: '',
        openTabs: [], // Array of { path, name, size, isModified }
        activeFile: null, // Points to active tab item
        get theme() {
            return window.Alpine ? window.Alpine.store('theme')?.mode || 'light' : 'light';
        },
        saveStatus: '', // 'Saved', 'Unsaved changes', 'Saving...'
        isCreatingFile: false,
        newFileName: '',
        searchFileQuery: '',

        // Editor Find & Replace State
        searchOpen: false,
        replaceOpen: false,
        searchQuery: '',
        replaceQuery: '',
        caseSensitive: false,
        wholeWord: false,
        isRegex: false,
        matchCount: 0,
        currentMatchIndex: 0,

        get isModified() {
            return this.activeFile ? !!this.activeFile.isModified : false;
        },

        get hasUnsavedChanges() {
            return this.openTabs.some(t => t.isModified);
        },

        navigateWithCheck(url) {
            if (this.hasUnsavedChanges) {
                const unsavedTab = this.openTabs.find(t => t.isModified);
                const fileName = unsavedTab ? unsavedTab.name : 'one or more files';
                if (window.confirmModal) {
                    window.confirmModal.show({
                        title: 'Leave Editor',
                        message: `You have unsaved changes in "${fileName}". Are you sure you want to leave without saving?`,
                        key: fileName,
                        metaLabel: 'Unsaved File',
                        confirmText: 'Leave without saving',
                        type: 'danger',
                        onConfirm: () => {
                            window.location.href = url;
                        }
                    });
                    return;
                }
            }
            window.location.href = url;
        },

        init() {
            this.loadTree();

            // Native browser guard for closing tab/reloading page
            window.addEventListener('beforeunload', (e) => {
                if (this.hasUnsavedChanges) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });

            window.addEventListener('app-theme-changed', (e) => {
                if (rawEditorView && this.activeFile) {
                    const currentDoc = rawEditorView.state.doc.toString();
                    this.mountCodeMirror(currentDoc, this.activeFile.name);
                }
            });
            window.addEventListener('keydown', (e) => {
                // Ctrl+S / Cmd+S: Save file
                if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S')) {
                    e.preventDefault();
                    if (this.activeFile && !this.saving) {
                        this.saveFile();
                    }
                }
                // Ctrl+F / Cmd+F: Open search
                if ((e.ctrlKey || e.metaKey) && (e.key === 'f' || e.key === 'F')) {
                    if (this.activeFile) {
                        e.preventDefault();
                        this.openEditorSearch(false);
                    }
                }
                // Ctrl+H / Cmd+H: Open replace
                if ((e.ctrlKey || e.metaKey) && (e.key === 'h' || e.key === 'H')) {
                    if (this.activeFile) {
                        e.preventDefault();
                        this.openEditorSearch(true);
                    }
                }
                // Escape closes search widget if open
                if (e.key === 'Escape' && this.searchOpen) {
                    this.closeEditorSearch();
                }
            });
        },

        openEditorSearch(withReplace = false) {
            this.searchOpen = true;
            if (withReplace) {
                this.replaceOpen = true;
            }
            if (rawEditorView) {
                const selection = rawEditorView.state.sliceDoc(
                    rawEditorView.state.selection.main.from,
                    rawEditorView.state.selection.main.to
                );
                if (selection && selection.length > 0 && !selection.includes('\n')) {
                    this.searchQuery = selection;
                }
                this.updateSearchQuery();
            }
            this.$nextTick(() => {
                const input = document.getElementById(withReplace ? 'editor-replace-input' : 'editor-search-input') 
                    || document.getElementById('editor-search-input');
                if (input) {
                    input.focus();
                    input.select();
                }
            });
        },

        closeEditorSearch() {
            this.searchOpen = false;
            if (rawEditorView) {
                rawEditorView.dispatch({
                    effects: setSearchQuery.of(new SearchQuery({ search: '' }))
                });
                rawEditorView.focus();
            }
        },

        toggleReplace() {
            this.replaceOpen = !this.replaceOpen;
            if (this.replaceOpen) {
                this.$nextTick(() => {
                    const input = document.getElementById('editor-replace-input');
                    if (input) input.focus();
                });
            }
        },

        toggleCaseSensitive() {
            this.caseSensitive = !this.caseSensitive;
            this.updateSearchQuery();
        },

        toggleWholeWord() {
            this.wholeWord = !this.wholeWord;
            this.updateSearchQuery();
        },

        toggleRegex() {
            this.isRegex = !this.isRegex;
            this.updateSearchQuery();
        },

        updateSearchQuery() {
            if (!rawEditorView) return;
            const q = this.searchQuery;
            rawEditorView.dispatch({
                effects: setSearchQuery.of(new SearchQuery({
                    search: q,
                    caseSensitive: this.caseSensitive,
                    wholeWord: this.wholeWord,
                    regexp: this.isRegex,
                    replace: this.replaceQuery
                }))
            });
            this.calculateMatchCount();
        },

        calculateMatchCount() {
            if (!rawEditorView || !this.searchQuery) {
                this.matchCount = 0;
                this.currentMatchIndex = 0;
                return;
            }
            try {
                const doc = rawEditorView.state.doc;
                let count = 0;
                let activeIdx = 0;
                const currentFrom = rawEditorView.state.selection.main.from;
                const currentTo = rawEditorView.state.selection.main.to;

                if (this.isRegex) {
                    let pat = this.searchQuery;
                    if (this.wholeWord) pat = '\\b(?:' + pat + ')\\b';
                    const cursor = new RegExpCursor(doc, pat, { ignoreCase: !this.caseSensitive });
                    while (!cursor.next().done) {
                        count++;
                        if (cursor.value.from === currentFrom && cursor.value.to === currentTo) {
                            activeIdx = count;
                        }
                    }
                } else if (this.wholeWord) {
                    let escaped = this.searchQuery.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                    const cursor = new RegExpCursor(doc, '\\b' + escaped + '\\b', { ignoreCase: !this.caseSensitive });
                    while (!cursor.next().done) {
                        count++;
                        if (cursor.value.from === currentFrom && cursor.value.to === currentTo) {
                            activeIdx = count;
                        }
                    }
                } else {
                    const cursor = new SearchCursor(doc, this.searchQuery, 0, doc.length, this.caseSensitive ? undefined : x => x.toLowerCase());
                    while (!cursor.next().done) {
                        count++;
                        if (cursor.value.from === currentFrom && cursor.value.to === currentTo) {
                            activeIdx = count;
                        }
                    }
                }
                this.matchCount = count;
                this.currentMatchIndex = activeIdx;
            } catch (e) {
                this.matchCount = 0;
                this.currentMatchIndex = 0;
            }
        },

        findNextMatch() {
            if (!rawEditorView) return;
            this.updateSearchQuery();
            findNext(rawEditorView);
            this.calculateMatchCount();
        },

        findPrevMatch() {
            if (!rawEditorView) return;
            this.updateSearchQuery();
            findPrevious(rawEditorView);
            this.calculateMatchCount();
        },

        replaceCurrent() {
            if (!rawEditorView) return;
            this.updateSearchQuery();
            replaceNext(rawEditorView);
            this.calculateMatchCount();
        },

        replaceAllMatches() {
            if (!rawEditorView) return;
            this.updateSearchQuery();
            replaceAll(rawEditorView);
            this.calculateMatchCount();
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
            if (this.activeFile && rawEditorView) {
                tabContents.set(this.activeFile.path, rawEditorView.state.doc.toString());
            }

            this.fileLoading = true;
            try {
                const res = await fetch(`/api/deployments/${this.deploymentId}/files/read?path=${encodeURIComponent(file.path)}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.success) {
                    const content = data.data.content || '';
                    tabContents.set(file.path, content);

                    const newTab = {
                        path: file.path,
                        name: file.name,
                        size: file.size ?? 0,
                        isModified: false
                    };

                    this.openTabs.push(newTab);
                    this.activeFile = newTab;
                    this.mountCodeMirror(content, newTab.name);
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
            if (this.activeFile && rawEditorView) {
                tabContents.set(this.activeFile.path, rawEditorView.state.doc.toString());
            }

            this.activeFile = tab;
            const content = tabContents.get(tab.path) || '';
            this.mountCodeMirror(content, tab.name);
            this.saveStatus = tab.isModified ? 'Unsaved changes' : 'Saved';
        },

        closeTab(tab, event) {
            if (event) {
                event.stopPropagation();
            }

            const executeClose = () => {
                tabContents.delete(tab.path);
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
                        if (rawEditorView) {
                            rawEditorView.destroy();
                            rawEditorView = null;
                        }
                        const container = document.getElementById('codemirror-editor');
                        if (container) container.innerHTML = '';
                        this.saveStatus = '';
                    }
                }
            };

            if (tab.isModified) {
                if (window.confirmModal) {
                    window.confirmModal.show({
                        title: 'Discard Unsaved Changes',
                        message: 'This file has unsaved changes that will be lost if you close it without saving.',
                        key: tab.name,
                        metaLabel: 'File',
                        confirmText: 'Discard & Close',
                        type: 'danger',
                        onConfirm: () => {
                            executeClose();
                        }
                    });
                    return;
                }

                const discard = confirm(`"${tab.name}" has unsaved changes. Discard and close?`);
                if (!discard) return;
            }

            executeClose();
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
                    return [htmlWithJsLanguage, javascript().support, autoCloseTags];
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
            if (rawEditorView) {
                rawEditorView.destroy();
                rawEditorView = null;
            }

            const extensions = [
                lineNumbers(),
                highlightActiveLineGutter(),
                highlightSpecialChars(),
                history(),
                foldGutter(),
                drawSelection(),
                dropCursor(),
                EditorState.allowMultipleSelections.of(true),
                syntaxHighlighting(defaultHighlightStyle, { fallback: true }),
                closeBrackets(),
                autocompletion({
                    activateOnTyping: true,
                    activateOnTypingDelay: 100,
                    maxRenderedOptions: 30
                }),
                highlightActiveLine(),
                search({
                    createPanel: () => ({ dom: document.createElement('div') }) // Hide default DOM panel since we have a custom top-right widget
                }),
                keymap.of([
                    ...closeBracketsKeymap,
                    ...completionKeymap,
                    {
                        key: 'Mod-f',
                        run: () => {
                            this.openEditorSearch(false);
                            return true;
                        },
                        scope: 'editor search-panel'
                    },
                    {
                        key: 'Mod-h',
                        run: () => {
                            this.openEditorSearch(true);
                            return true;
                        },
                        scope: 'editor search-panel'
                    },
                    {
                        key: 'Escape',
                        run: () => {
                            if (this.searchOpen) {
                                this.closeEditorSearch();
                                return true;
                            }
                            return false;
                        },
                        scope: 'editor search-panel'
                    },
                    {
                        key: 'F3',
                        run: () => {
                            this.findNextMatch();
                            return true;
                        },
                        shift: () => {
                            this.findPrevMatch();
                            return true;
                        }
                    },
                    ...defaultKeymap,
                    ...historyKeymap,
                    ...foldKeymap
                ]),
                this.getLanguageExtension(filename),
                EditorView.updateListener.of((update) => {
                    if (update.docChanged && !isCurrentlyModified) {
                        isCurrentlyModified = true;
                        requestAnimationFrame(() => {
                            if (this.activeFile) {
                                this.activeFile.isModified = true;
                            }
                            this.saveStatus = 'Unsaved changes';
                        });
                    }
                    if (update.selectionSet || update.docChanged) {
                        if (this.searchOpen) {
                            this.calculateMatchCount();
                        }
                    }
                }),
                EditorView.theme({
                    '&': { height: '100%', fontSize: '13.5px', fontFamily: 'JetBrains Mono, Menlo, Monaco, Consolas, monospace' },
                    '.cm-scroller': {
                        overflow: 'auto',
                        scrollbarColor: this.theme === 'dark' ? '#3e4451 #1e1e22' : '#cbd5e1 #f8fafc',
                        scrollbarWidth: 'thin'
                    },
                    '.cm-scroller::-webkit-scrollbar': {
                        width: '10px',
                        height: '10px',
                        backgroundColor: this.theme === 'dark' ? '#1e1e22' : '#f8fafc'
                    },
                    '.cm-scroller::-webkit-scrollbar-track': {
                        backgroundColor: this.theme === 'dark' ? '#1e1e22' : '#f8fafc'
                    },
                    '.cm-scroller::-webkit-scrollbar-thumb': {
                        backgroundColor: this.theme === 'dark' ? '#3e4451' : '#cbd5e1',
                        borderRadius: '4px',
                        border: `2px solid ${this.theme === 'dark' ? '#1e1e22' : '#f8fafc'}`
                    },
                    '.cm-scroller::-webkit-scrollbar-thumb:hover': {
                        backgroundColor: this.theme === 'dark' ? '#5c6370' : '#94a3b8'
                    },
                    '.cm-scroller::-webkit-scrollbar-corner': {
                        backgroundColor: this.theme === 'dark' ? '#1e1e22' : '#f8fafc'
                    },
                    '.cm-content': { padding: '12px 0' }
                })
            ];

            if (this.theme === 'dark') {
                extensions.push(oneDark);
                extensions.push(EditorView.theme({
                    '&': { backgroundColor: '#1e1e22 !important' },
                    '.cm-gutters': { backgroundColor: '#1e1e22 !important', borderRight: '1px solid #2e2e36' },
                    '.cm-activeLine': { backgroundColor: '#25252b !important' },
                    '.cm-activeLineGutter': { backgroundColor: '#25252b !important' }
                }));
            }

            isCurrentlyModified = this.activeFile ? !!this.activeFile.isModified : false;

            rawEditorView = new EditorView({
                doc: content,
                extensions,
                parent: container
            });
        },

        async saveFile() {
            if (!this.activeFile || !rawEditorView || this.saving) return;

            this.saving = true;
            this.saveStatus = 'Saving';

            const content = rawEditorView.state.doc.toString();

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

        async triggerDownloadZip() {
            if (this.downloadingZip) return;
            this.downloadingZip = true;

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            try {
                // 1. Dispatch background queue job to prepare the ZIP
                const res = await fetch(`/api/deployments/${this.deploymentId}/files/request-zip`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    }
                });

                const data = await res.json();
                if (!data.success || !data.token) {
                    throw new Error(data.message || 'Failed to initiate archive preparation.');
                }

                const token = data.token;
                this.showToast('Preparing ZIP archive in the background...', 'info');

                // 2. Poll every 3 seconds to avoid server overhead
                const pollInterval = setInterval(async () => {
                    try {
                        const statusRes = await fetch(`/api/deployments/${this.deploymentId}/files/zip-status?token=${token}`, {
                            headers: { 'Accept': 'application/json' }
                        });

                        if (!statusRes.ok) {
                            clearInterval(pollInterval);
                            this.downloadingZip = false;
                            this.showToast('Archive preparation timed out or failed.', 'error');
                            return;
                        }

                        const statusData = await statusRes.json();

                        if (statusData.status === 'ready') {
                            clearInterval(pollInterval);
                            this.downloadingZip = false;
                            this.showToast('ZIP ready! Starting download...', 'success');

                            // 3. Hand off directly to browser's native download manager
                            const downloadUrl = `/deployments/${this.deployment.hash}/files/download?token=${token}`;
                            const link = document.createElement('a');
                            link.href = downloadUrl;
                            link.setAttribute('download', statusData.filename || `${this.deployment.domain}.zip`);
                            document.body.appendChild(link);
                            link.click();

                            setTimeout(() => {
                                if (link.parentNode) {
                                    document.body.removeChild(link);
                                }
                            }, 5000);
                        } else if (statusData.status === 'failed') {
                            clearInterval(pollInterval);
                            this.downloadingZip = false;
                            this.showToast(statusData.error || 'Failed to prepare ZIP.', 'error');
                        }
                    } catch (pollErr) {
                        // Keep polling on transient network hiccups
                    }
                }, 3000);

                // Stop polling after 5 minutes maximum
                setTimeout(() => {
                    clearInterval(pollInterval);
                    if (this.downloadingZip) {
                        this.downloadingZip = false;
                        this.showToast('Archive preparation took too long.', 'error');
                    }
                }, 300000);

            } catch (err) {
                this.downloadingZip = false;
                this.showToast(err.message || 'Error requesting ZIP download', 'error');
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
