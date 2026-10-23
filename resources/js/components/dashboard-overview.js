// resources/js/components/dashboard-overview.js

import { fetchCurrentUser } from '../services/auth-service.js';
import {
    fetchDeployments,
    createDeployment,
    deleteDeployment,
    bulkDeleteDeployments,
} from '../services/deployment-service.js';
import { getServers } from '../services/server-service.js';

export function dashboardOverview(initialDeployments = [], initialServers = []) {
    const hasInitialData = (Array.isArray(initialDeployments) && initialDeployments.length > 0) || 
                           (Array.isArray(initialServers) && initialServers.length > 0);

    return {
        userName: '',
        isLoading: !hasInitialData,
        isSubmitting: false,
        showModal: false,
        searchQuery: '',
        selectedDeploymentIds: [],

        // Data arrays initialized with server-provided cached data
        deployments: Array.isArray(initialDeployments) ? initialDeployments : [],
        servers: Array.isArray(initialServers) ? initialServers.filter(s => Boolean(s.is_active)) : [],

        // Drawer Form state
        form: {
            domain: '',
            server_id: '',
            version: 'non-www',
            source_type: 'url',
            archive_url: '',
            archiveFile: null,
        },
        serverDropdownOpen: false,
        get selectedServer() {
            if (!this.form.server_id && this.servers.length > 0) {
                this.form.server_id = this.servers[0].server_id;
            }
            return this.servers.find(s => String(s.server_id) === String(this.form.server_id)) || null;
        },
        errors: {},

        // Polling timer
        pollInterval: null,

        async init() {
            if (this.servers.length > 0 && !this.form.server_id) {
                this.form.server_id = this.servers[0].server_id;
            }
            try {
                fetchCurrentUser()
                    .then(user => {
                        if (user) {
                            this.userName = user.name || 'User';
                        }
                    })
                    .catch(() => { });

                // If no initial data was passed, load asynchronously and show loader
                if (!hasInitialData) {
                    await Promise.allSettled([
                        this.loadServers(),
                        this.loadDeployments(),
                    ]);
                } else {
                    // Refresh servers in the background to ensure fresh state
                    this.loadServers().catch(() => { });
                }

                if (!this.form.server_id && this.servers.length > 0) {
                    this.form.server_id = this.servers[0].server_id;
                }
            } catch (error) {
                console.error('Failed to initialize dashboard:', error);
            } finally {
                this.isLoading = false;
            }

            // Periodically refresh deployments from /api every 5 seconds if active deployments exist
            this.pollInterval = setInterval(() => {
                if (this.queueItems.length > 0) {
                    this.loadDeployments(false, true).catch(() => { });
                }
            }, 5000);
        },

        destroy() {
            if (this.pollInterval) {
                clearInterval(this.pollInterval);
            }
        },

        async loadServers(forceRefresh = false) {
            try {
                const res = await getServers(forceRefresh);
                const list = res.data || res || [];
                if (Array.isArray(list)) {
                    this.servers = list.filter(s => Boolean(s.is_active));
                    const selectedServerStillActive = this.servers.some(
                        s => String(s.server_id) === String(this.form.server_id)
                    );
                    if (!selectedServerStillActive) {
                        this.form.server_id = this.servers.length > 0
                            ? this.servers[0].server_id
                            : '';
                    }
                }
            } catch (err) {
                console.warn('Could not load servers from /api:', err);
            }
        },

        async loadDeployments(showSpinner = false, forceRefresh = false) {
            if (showSpinner) this.isLoading = true;
            try {
                const res = await fetchDeployments(forceRefresh);
                this.deployments = Array.isArray(res?.data) ? res.data : (Array.isArray(res) ? res : []);
            } catch (err) {
                console.warn('Could not load deployments from /api:', err);
                this.deployments = [];
            } finally {
                if (showSpinner) this.isLoading = false;
            }
        },

        // Pagination state
        currentPage: 1,
        perPage: 9,

        // Filtered domains based on search
        get filteredDeployments() {
            let list = [...this.deployments];
            if (this.searchQuery.trim() !== '') {
                const q = this.searchQuery.toLowerCase().trim();
                list = list.filter(d =>
                    (d.domain && d.domain.toLowerCase().includes(q)) ||
                    (d.server?.name && d.server.name.toLowerCase().includes(q)) ||
                    (d.current_step && d.current_step.toLowerCase().includes(q))
                );
            }
            return list;
        },

        // Active / Queue deployments (pending, processing)
        get queueItems() {
            return this.deployments.filter(d => d.status === 'pending' || d.status === 'processing');
        },

        // Active deployment that is first in line of the queue
        get activeQueueDeployment() {
            return this.queueItems.length > 0 ? this.queueItems[0] : null;
        },

        // Successful / Completed deployments
        get successfulDeployments() {
            return this.filteredDeployments.filter(d => d.status === 'completed');
        },

        // Deployments to display in the main list/grid:
        // Completed deployments plus the first-in-line queue deployment (if matches search filter)
        get displayedDeployments() {
            const list = [...this.successfulDeployments];
            const active = this.activeQueueDeployment;
            if (active) {
                // Check if active deployment matches current search filter
                const matchesSearch = this.filteredDeployments.some(d => d.id === active.id);
                if (matchesSearch && !list.some(d => d.id === active.id)) {
                    list.unshift(active);
                }
            }
            return list;
        },

        // Pagination computed properties
        get totalPages() {
            return Math.ceil(this.displayedDeployments.length / this.perPage) || 1;
        },

        get paginatedDeployments() {
            const page = Math.min(Math.max(1, this.currentPage), this.totalPages);
            const start = (page - 1) * this.perPage;
            return this.displayedDeployments.slice(start, start + this.perPage);
        },

        get paginationFrom() {
            if (this.displayedDeployments.length === 0) return 0;
            return (Math.min(this.currentPage, this.totalPages) - 1) * this.perPage + 1;
        },

        get paginationTo() {
            return Math.min(Math.min(this.currentPage, this.totalPages) * this.perPage, this.displayedDeployments.length);
        },

        goToPage(page) {
            if (page >= 1 && page <= this.totalPages) {
                this.currentPage = page;
            }
        },

        nextPage() {
            if (this.currentPage < this.totalPages) {
                this.currentPage++;
            }
        },

        prevPage() {
            if (this.currentPage > 1) {
                this.currentPage--;
            }
        },

        get selectableDeployments() {
            return this.paginatedDeployments.filter(item => item.status !== 'processing');
        },

        get allVisibleDeploymentsSelected() {
            return this.selectableDeployments.length > 0
                && this.selectableDeployments.every(item => this.selectedDeploymentIds.includes(item.id));
        },

        toggleDeploymentSelection(id) {
            this.selectedDeploymentIds = this.selectedDeploymentIds.includes(id)
                ? this.selectedDeploymentIds.filter(selectedId => selectedId !== id)
                : [...this.selectedDeploymentIds, id];
        },

        toggleVisibleDeploymentSelection() {
            const visibleIds = this.selectableDeployments.map(item => item.id);
            if (this.allVisibleDeploymentsSelected) {
                this.selectedDeploymentIds = this.selectedDeploymentIds.filter(id => !visibleIds.includes(id));
                return;
            }
            this.selectedDeploymentIds = [...new Set([...this.selectedDeploymentIds, ...visibleIds])];
        },

        deleteDeploymentPrompt(item) {
            if (item.status === 'processing') {
                window.toast?.warning('Cancel this deployment before deleting it.');
                return;
            }

            window.confirmModal?.show({
                title: 'Delete Deployment and RunCloud App',
                message: 'This permanently deletes the RunCloud web application, its server files, and the local deployment record. This cannot be undone.',
                key: item.domain,
                metaLabel: 'Domain',
                confirmText: 'Delete Everywhere',
                type: 'danger',
                onConfirm: async () => {
                    try {
                        await deleteDeployment(item.hash || item.id);
                        this.deployments = this.deployments.filter(deployment => deployment.id !== item.id);
                        this.selectedDeploymentIds = this.selectedDeploymentIds.filter(id => id !== item.id);
                        window.toast?.success('Deployment and RunCloud web application deleted.');
                    } catch (error) {
                        window.toast?.error(error.message || 'Failed to delete deployment.');
                        throw error;
                    }
                },
            });
        },

        deleteSelectedDeploymentsPrompt() {
            const ids = [...this.selectedDeploymentIds];
            if (ids.length === 0) return;

            window.confirmModal?.show({
                title: 'Bulk Delete Deployments',
                message: `This permanently deletes ${ids.length} RunCloud web application(s), their server files, and local deployment records. This cannot be undone.`,
                key: `${ids.length} selected deployment(s)`,
                metaLabel: 'Selection',
                confirmText: 'Delete All Everywhere',
                type: 'danger',
                onConfirm: async () => {
                    try {
                        const response = await bulkDeleteDeployments(ids);
                        const processedIds = response?.data?.processed_ids || [];
                        const skipped = response?.data?.skipped || [];
                        this.deployments = this.deployments.filter(item => !processedIds.includes(item.id));
                        this.selectedDeploymentIds = this.selectedDeploymentIds.filter(id => !processedIds.includes(id));

                        if (skipped.length > 0) {
                            window.toast?.warning(`${processedIds.length} deleted; ${skipped.length} could not be deleted.`);
                        } else {
                            window.toast?.success(`${processedIds.length} deployment(s) deleted everywhere.`);
                        }
                    } catch (error) {
                        window.toast?.error(error.message || 'Bulk deletion failed.');
                        throw error;
                    }
                },
            });
        },

        isItemActive(item) {
            return item.status === 'pending' || item.status === 'processing';
        },

        // Failed deployments
        get failedDeployments() {
            return this.deployments.filter(d => d.status === 'failed');
        },

        // Calculate progress % based on current_step
        getDeploymentProgress(deployment) {
            if (deployment.status === 'completed') return 100;
            if (deployment.status === 'failed') return 100;

            const step = deployment.current_step || '';
            switch (step) {
                case 'downloading_archive':
                case 'preparing_archive':
                    return 25;
                case 'provisioning_web_app':
                    return 50;
                case 'uploading_and_extracting_archive':
                    return 75;
                case 'configuring_cloudflare_workers':
                    return 90;
                case 'registering_search_console':
                case 'submitting_to_google_search_console':
                    return 95;
                default:
                    return deployment.status === 'processing' ? 40 : 10;
            }
        },

        // Format step label
        getStepLabel(step) {
            if (!step) return 'In Queue';
            const labels = {
                'downloading_archive': 'Downloading Archive',
                'preparing_archive': 'Preparing Archive',
                'provisioning_web_app': 'Creating WebApp',
                'uploading_and_extracting_archive': 'Uploading Files',
                'configuring_cloudflare_workers': 'Configuring Cloudflare',
                'registering_search_console': 'Registering with Google Search Console',
                'submitting_to_google_search_console': 'GSC Submission',
                'completed': 'Done',
                'failed': 'Failed',
            };
            return labels[step] || step.replace(/_/g, ' ');
        },

        // Format domain title without TLD (.com, .net, etc.)
        formatDomainName(domain) {
            if (!domain) return '';
            // Remove protocol and trailing slashes if present
            let cleaned = domain.replace(/^https?:\/\//i, '').replace(/\/.*$/, '');
            // Strip the TLD (e.g. .com, .co.uk, .org, .net, etc.)
            const parts = cleaned.split('.');
            if (parts.length > 1) {
                // If it's a domain like foo.com or foo.co.uk
                // If the first part is 'www', discard it or handle
                if (parts[0].toLowerCase() === 'www' && parts.length > 2) {
                    return parts[1];
                }
                return parts[0];
            }
            return cleaned;
        },

        // Format relative or date string
        formatDate(dateString) {
            if (!dateString) return 'Just now';
            const date = new Date(dateString);
            if (isNaN(date.getTime())) return 'Just now';

            const now = new Date();
            const diffMs = now - date;
            const diffMins = Math.floor(diffMs / 60000);
            const diffHours = Math.floor(diffMins / 60);
            const diffDays = Math.floor(diffHours / 24);

            if (diffMins < 1) return 'Just now';
            if (diffMins < 60) return `${diffMins}m ago`;
            if (diffHours < 24) return `${diffHours}h ago`;
            if (diffDays < 7) return `${diffDays}d ago`;

            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        },

        // Drawer Controls
        async openModal() {
            this.showModal = true;
            this.serverDropdownOpen = false;
            this.errors = {};
            await this.loadServers(true);
            const defaultServerId = (this.form.server_id && this.servers.some(s => String(s.server_id) === String(this.form.server_id)))
                ? this.form.server_id
                : (this.servers.length > 0 ? this.servers[0].server_id : '');

            this.form = {
                domain: '',
                server_id: defaultServerId,
                version: 'non-www',
                source_type: 'url',
                archive_url: '',
                archiveFile: null,
            };
        },

        closeModal() {
            this.showModal = false;
            this.serverDropdownOpen = false;
            this.errors = {};
        },

        handleFileUpload(event) {
            const file = event.target.files?.[0];
            if (file) {
                this.form.archiveFile = file;
            }
        },

        async submitDeployment() {
            console.log('[submitDeployment] Starting submission with form data:', JSON.parse(JSON.stringify(this.form)));
            this.isSubmitting = true;
            this.errors = {};

            if (!this.form.domain || !this.form.domain.trim()) {
                console.warn('[submitDeployment] Validation failed: domain is required');
                this.errors = { domain: ['Domain is required.'] };
                this.isSubmitting = false;
                return;
            }

            if (!this.form.server_id) {
                console.warn('[submitDeployment] Validation failed: server_id is empty');
                this.errors = { server_id: ['Please choose an active server.'] };
                this.isSubmitting = false;
                return;
            }

            try {
                let payload;
                if (this.form.source_type === 'upload' && this.form.archiveFile) {
                    const formData = new FormData();
                    formData.append('domain', this.form.domain.trim());
                    formData.append('server_id', this.form.server_id);
                    formData.append('version', this.form.version);
                    formData.append('archive', this.form.archiveFile);
                    payload = formData;
                } else {
                    payload = {
                        domain: this.form.domain.trim(),
                        server_id: parseInt(this.form.server_id, 10),
                        version: this.form.version,
                    };
                    if (this.form.archive_url && this.form.archive_url.trim()) {
                        payload.archive_url = this.form.archive_url.trim();
                    }
                }

                console.log('[submitDeployment] Sending POST /api/deployments payload:', payload);
                const response = await createDeployment(payload);
                console.log('[submitDeployment] Received response:', response);

                // Add newly created deployment to local list
                const created = response.data || response;
                if (created && created.id) {
                    // Enrich server relationship if missing
                    if (!created.server && this.form.server_id) {
                        const matchingServer = this.servers.find(s => s.server_id == this.form.server_id);
                        if (matchingServer) {
                            created.server = matchingServer;
                        }
                    }
                    this.deployments.unshift(created);
                } else {
                    await this.loadDeployments(false);
                }

                this.closeModal();
            } catch (error) {
                console.error('Deployment error:', error);
                if (error.status === 422 && error.errors) {
                    this.errors = error.errors;
                } else {
                    this.errors = { general: error.message || 'Failed to trigger deployment.' };
                }
            } finally {
                this.isSubmitting = false;
            }
        }
    };
}
