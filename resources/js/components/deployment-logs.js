// resources/js/components/deployment-logs.js

import {
    fetchDeployment,
    fetchDeploymentLogs,
    fetchDeploymentSubdomainFolders,
    fetchDeploymentSubdomainHtaccessStatus,
    previewDeploymentSubdomainHtaccess,
    applyDeploymentSubdomainHtaccess,
    retryDeployment,
    cancelDeployment,
    deleteDeployment,
} from '../services/deployment-service.js';

export function deploymentLogsPage(deploymentId, initialDeployment = null) {
    return {
        deploymentId: deploymentId,
        deployment: initialDeployment,
        logs: [],
        isLoading: true,
        isPolling: false,
        pollInterval: null,
        searchQuery: '',
        statusFilter: 'all',
        isRetrying: false,
        isCancelling: false,
        isDeleting: false,
        autoScroll: true,
        subdomainFolders: [],
        allSubdomainFolders: [],
        selectedSubdomainFolders: [],
        configuredSubdomains: [],
        htaccessStatusLoaded: false,
        isLoadingSubdomainFolders: false,
        isApplyingHtaccess: false,
        isPreviewingHtaccess: false,
        showHtaccessPreview: false,
        htaccessPreviewData: null,
        htaccessFeedback: null,
        subdomainFoldersMeta: {
            current_page: 1,
            last_page: 1,
            per_page: 10,
            total: 0,
        },

        async init() {
            await this.loadData();

            // Subdomain SSL can continue after the main deployment completes.
            this.pollInterval = setInterval(async () => {
                if (this.deployment && (this.deployment.status === 'pending' || this.deployment.status === 'processing' || this.hasPendingSubdomainSsl)) {
                    await this.loadData(false);
                }
            }, 3000);
        },

        get hasPendingSubdomainSsl() {
            const latestStatuses = new Map();
            for (const log of this.logs) {
                if (log.step_name !== 'configuring_subdomain_ssl') continue;
                const domain = (log.message || '').split(':', 1)[0];
                if (/^[a-z0-9-]+(?:\.[a-z0-9-]+)+$/i.test(domain)) {
                    latestStatuses.set(domain, log.status);
                }
            }
            return [...latestStatuses.values()].some(status => status === 'in_progress');
        },

        syncSubdomainSslFeedback() {
            const runcloud = this.htaccessFeedback?.runcloud;
            if (!runcloud) return;
            for (const item of [...(runcloud.added || []), ...(runcloud.skipped || [])]) {
                const log = [...this.logs].reverse().find(entry => entry.step_name === 'configuring_subdomain_ssl'
                    && (entry.message || '').startsWith(`${item.subdomain}: `));
                if (!log) continue;
                item.ssl = { in_progress: 'queued', success: 'configured', error: 'failed' }[log.status] || item.ssl;
                if (log.status === 'error') item.ssl_error = log.message;
            }
        },

        destroy() {
            if (this.pollInterval) {
                clearInterval(this.pollInterval);
            }
        },

        async loadData(showLoader = true) {
            if (showLoader) {
                this.isLoading = true;
            }
            try {
                const [deploymentRes, logsRes] = await Promise.all([
                    fetchDeployment(this.deploymentId),
                    fetchDeploymentLogs(this.deploymentId, { per_page: 100, sort_direction: 'desc' }),
                ]);

                this.deployment = deploymentRes.data || deploymentRes;
                this.logs = [...(logsRes.data || logsRes || [])].reverse();
                this.syncSubdomainSslFeedback();

                await this.loadSubdomainFolders(this.subdomainFoldersMeta.current_page || 1);

                if (this.autoScroll) {
                    this.$nextTick(() => {
                        this.scrollToBottom();
                    });
                }
            } catch (error) {
                console.error('Failed to load deployment details or logs:', error);
                if (window.toast && showLoader) {
                    window.toast.error(error.message || 'Failed to load deployment logs.');
                }
            } finally {
                if (showLoader) {
                    this.isLoading = false;
                }
            }
        },

        async loadSubdomainFolders(page = 1) {
            this.isLoadingSubdomainFolders = true;
            try {
                const response = await fetchDeploymentSubdomainFolders(this.deploymentId, {
                    page,
                    per_page: this.subdomainFoldersMeta.per_page || 10,
                });

                this.subdomainFolders = response.data || [];
                this.allSubdomainFolders = response.all_folders || [];
                this.subdomainFoldersMeta = {
                    current_page: response.meta?.current_page ?? page,
                    last_page: response.meta?.last_page ?? 1,
                    per_page: response.meta?.per_page ?? 10,
                    total: response.meta?.total ?? 0,
                };

                if (this.subdomainFoldersMeta.total > 0 && !this.htaccessStatusLoaded) {
                    await this.loadHtaccessStatus();
                }
            } catch (error) {
                console.error('Failed to load subdomain folders:', error);
                if (window.toast) {
                    window.toast.error(error.message || 'Failed to load subdomain folders.');
                }
            } finally {
                this.isLoadingSubdomainFolders = false;
            }
        },

        async loadHtaccessStatus() {
            try {
                const res = await fetchDeploymentSubdomainHtaccessStatus(this.deploymentId);
                if (res?.data) {
                    this.configuredSubdomains = res.data.configured_subdomains || [];
                    this.htaccessStatusLoaded = true;

                    // If user has not modified selection yet, pre-check subdomains currently in .htaccess
                    if (this.selectedSubdomainFolders.length === 0 && this.configuredSubdomains.length > 0) {
                        this.selectedSubdomainFolders = [...this.configuredSubdomains];
                    }
                }
            } catch (e) {
                // Ignore silent status check errors
                console.warn('Could not inspect .htaccess:', e);
            }
        },

        getSubdomainLabel(folderName) {
            if (!folderName) return '';
            const name = String(typeof folderName === 'object' ? folderName.name : folderName).trim().toLowerCase();
            return name.endsWith('-sub') ? name.slice(0, -4) : name;
        },

        isFolderSelected(folder) {
            const label = this.getSubdomainLabel(folder);
            return this.selectedSubdomainFolders.includes(label);
        },

        toggleFolder(folder) {
            const label = this.getSubdomainLabel(folder);
            if (this.selectedSubdomainFolders.includes(label)) {
                this.selectedSubdomainFolders = this.selectedSubdomainFolders.filter(item => item !== label);
            } else {
                this.selectedSubdomainFolders.push(label);
            }
        },

        get availableSubdomainLabels() {
            const list = this.allSubdomainFolders && this.allSubdomainFolders.length > 0
                ? this.allSubdomainFolders
                : this.subdomainFolders.map(f => f.name);
            return Array.from(new Set(list.map(f => this.getSubdomainLabel(f)))).filter(Boolean);
        },

        get isAllSubdomainsSelected() {
            const all = this.availableSubdomainLabels;
            return all.length > 0 && all.every(label => this.selectedSubdomainFolders.includes(label));
        },

        get isSubdomainsIndeterminate() {
            const all = this.availableSubdomainLabels;
            const count = all.filter(label => this.selectedSubdomainFolders.includes(label)).length;
            return count > 0 && count < all.length;
        },

        toggleSelectAll() {
            if (this.isAllSubdomainsSelected) {
                this.selectedSubdomainFolders = [];
            } else {
                this.selectedSubdomainFolders = [...this.availableSubdomainLabels];
            }
        },

        isConfiguredInHtaccess(folder) {
            const label = this.getSubdomainLabel(folder);
            return this.configuredSubdomains.includes(label);
        },

        async previewHtaccessRules() {
            if (this.selectedSubdomainFolders.length === 0) return;
            this.isPreviewingHtaccess = true;
            try {
                const res = await previewDeploymentSubdomainHtaccess(this.deploymentId, this.selectedSubdomainFolders);
                this.htaccessPreviewData = res.data;
                this.showHtaccessPreview = true;
            } catch (error) {
                console.error('Failed to preview .htaccess rules:', error);
                if (window.toast) {
                    window.toast.error(error.message || 'Failed to generate .htaccess preview.');
                }
            } finally {
                this.isPreviewingHtaccess = false;
            }
        },

        async applyHtaccessRules() {
            if (this.selectedSubdomainFolders.length === 0 || this.isApplyingHtaccess) return;
            this.isApplyingHtaccess = true;
            this.htaccessFeedback = null;
            try {
                const res = await applyDeploymentSubdomainHtaccess(this.deploymentId, this.selectedSubdomainFolders);
                const data = res.data || {};
                this.configuredSubdomains = data.configured_subdomains || [...this.selectedSubdomainFolders];
                this.htaccessFeedback = {
                    type: 'success',
                    message: res.message || 'Subdomain routing rules applied successfully.',
                    added: data.added || [],
                    updated: data.updated || [],
                    removed: data.removed || [],
                    backup_file: data.backup_file || '',
                    notice: data.notice || null,
                    runcloud: data.runcloud || null,
                };
                this.showHtaccessPreview = false;
                if (window.toast) {
                    window.toast.success(res.message || 'Subdomain routing rules applied.');
                }
                // Reload logs to display the new configuring_subdomain_htaccess step
                await this.loadData(false);
            } catch (error) {
                console.error('Failed to apply .htaccess rules:', error);
                this.htaccessFeedback = {
                    type: 'error',
                    message: error.message || 'Failed to apply .htaccess rules.',
                };
                if (window.toast) {
                    window.toast.error(error.message || 'Failed to apply .htaccess rules.');
                }
            } finally {
                this.isApplyingHtaccess = false;
            }
        },

        async goToSubdomainFoldersPage(page) {
            if (page < 1 || page > this.subdomainFoldersMeta.last_page || this.isLoadingSubdomainFolders) {
                return;
            }

            await this.loadSubdomainFolders(page);
        },

        get subdomainFoldersFrom() {
            if (this.subdomainFoldersMeta.total === 0) {
                return 0;
            }

            return ((this.subdomainFoldersMeta.current_page - 1) * this.subdomainFoldersMeta.per_page) + 1;
        },

        get subdomainFoldersTo() {
            return Math.min(
                this.subdomainFoldersMeta.current_page * this.subdomainFoldersMeta.per_page,
                this.subdomainFoldersMeta.total,
            );
        },

        get filteredLogs() {
            let list = this.logs.filter(l => l.step_name !== 'detecting_subdomains');

            if (this.statusFilter !== 'all') {
                list = list.filter(l => l.status === this.statusFilter);
            }

            if (this.searchQuery.trim() !== '') {
                const q = this.searchQuery.toLowerCase().trim();
                list = list.filter(l =>
                    (l.message && l.message.toLowerCase().includes(q)) ||
                    (l.step_name && l.step_name.toLowerCase().includes(q))
                );
            }

            return list;
        },

        get duration() {
            if (!this.deployment?.created_at) return '—';
            const start = new Date(this.deployment.created_at).getTime();
            const end = this.deployment.status === 'completed' || this.deployment.status === 'failed'
                ? new Date(this.deployment.updated_at || this.deployment.created_at).getTime()
                : Date.now();
            
            const seconds = Math.max(1, Math.round((end - start) / 1000));
            if (seconds < 60) {
                return `${seconds}s`;
            }
            const mins = Math.floor(seconds / 60);
            const remSecs = seconds % 60;
            return `${mins}m ${remSecs}s`;
        },

        get triggerText() {
            if (!this.deployment) return 'Dashboard';
            const user = this.deployment.user?.name ? `by ${this.deployment.user.name}` : '';
            return `Manually deployed ${user} via Dashboard`.trim();
        },

        get sourceText() {
            if (!this.deployment) return '—';
            if (this.deployment.archive_source === 'upload') {
                return this.deployment.archive_original_name || 'Uploaded Archive';
            }
            return this.deployment.archive_url || 'Wayback Machine';
        },

        formatDateTime(dateStr) {
            if (!dateStr) return '—';
            try {
                const d = new Date(dateStr);
                return d.toLocaleString('en-US', {
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: true
                });
            } catch (e) {
                return dateStr;
            }
        },

        formatTime(dateStr) {
            if (!dateStr) return '';
            try {
                const d = new Date(dateStr);
                return d.toLocaleTimeString('en-US', {
                    hour12: false,
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
            } catch (e) {
                return '';
            }
        },

        formatLabel(text) {
            if (!text) return '';
            return text.replace(/_/g, ' ').trim();
        },

        scrollToBottom() {
            const container = document.getElementById('log-terminal-output');
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        },

        copyAllLogs() {
            if (!navigator?.clipboard || this.filteredLogs.length === 0) return;
            const text = this.filteredLogs
                .map(l => `[${this.formatTime(l.created_at)}] [${(l.step_name || 'step').toUpperCase()}] [${(l.status || 'info').toUpperCase()}]: ${l.message}`)
                .join('\n');
            navigator.clipboard.writeText(text);
            window.toast?.success('Logs copied to clipboard!');
        },

        async triggerRetry() {
            if (this.isRetrying) return;
            this.isRetrying = true;
            try {
                await retryDeployment(this.deploymentId);
                window.toast?.success('Deployment retry queued.');
                await this.loadData(false);
            } catch (error) {
                window.toast?.error(error.message || 'Failed to retry deployment.');
            } finally {
                this.isRetrying = false;
            }
        },

        async triggerCancel() {
            if (this.isCancelling) return;

            const executeCancel = async () => {
                this.isCancelling = true;
                try {
                    await cancelDeployment(this.deploymentId);
                    window.toast?.success('Deployment cancelled.');
                    await this.loadData(false);
                } catch (error) {
                    window.toast?.error(error.message || 'Failed to cancel deployment.');
                } finally {
                    this.isCancelling = false;
                }
            };

            if (window.confirmModal) {
                window.confirmModal.show({
                    title: 'Cancel Deployment',
                    message: 'Are you sure you want to cancel this active deployment? Ongoing provisioning or build steps will be aborted.',
                    key: this.deployment?.domain || '',
                    metaLabel: 'Domain',
                    confirmText: 'Cancel Deployment',
                    type: 'danger',
                    onConfirm: executeCancel
                });
                return;
            }

            if (confirm('Are you sure you want to cancel this active deployment?')) {
                await executeCancel();
            }
        },

        triggerDelete() {
            if (this.isDeleting || !this.deployment) return;

            if (this.deployment.status === 'processing') {
                window.toast?.warning('Cancel this deployment before deleting it.');
                return;
            }

            window.confirmModal?.show({
                title: 'Delete Deployment and RunCloud App',
                message: 'This permanently deletes the RunCloud web application, its server files, and the local deployment record. This cannot be undone.',
                key: this.deployment.domain,
                metaLabel: 'Domain',
                confirmText: 'Delete Everywhere',
                type: 'danger',
                onConfirm: async () => {
                    this.isDeleting = true;
                    try {
                        await deleteDeployment(this.deploymentId);
                        window.toast?.success('Deployment and RunCloud web application deleted.');
                        window.location.href = '/dashboard';
                    } catch (error) {
                        window.toast?.error(error.message || 'Failed to delete deployment.');
                        throw error;
                    } finally {
                        this.isDeleting = false;
                    }
                },
            });
        }
    };
}
