// resources/js/components/deployment-logs.js

import {
    fetchDeployment,
    fetchDeploymentLogs,
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

        async init() {
            await this.loadData();

            // Set up polling while deployment is pending or processing
            this.pollInterval = setInterval(async () => {
                if (this.deployment && (this.deployment.status === 'pending' || this.deployment.status === 'processing')) {
                    await this.loadData(false);
                }
            }, 3000);
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
                    fetchDeploymentLogs(this.deploymentId, { per_page: 100, sort_direction: 'asc' })
                ]);

                this.deployment = deploymentRes.data || deploymentRes;
                this.logs = logsRes.data || logsRes || [];

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

        get filteredLogs() {
            let list = [...this.logs];

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
