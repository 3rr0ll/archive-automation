import { bulkSearchDeployments, bulkDeleteDeployments } from '../services/deployment-service.js';

export function bulkDeletePage() {
    return {
        isLoading: false,
        domainInput: '',
        isSearching: false,
        isDeleting: false,
        hasSearched: false,
        foundDeployments: [],
        missingDomains: [],
        selectedIds: [],
        searchQuery: '',
        statusFilter: 'all',
        sortBy: 'domain',
        currentPage: 1,
        perPage: 10,
        deletionSummary: null,

        get parsedInputDomains() {
            if (!this.domainInput) return [];
            return this.domainInput
                .split('\n')
                .map(line => line.trim().toLowerCase().replace(/^https?:\/\//i, '').replace(/\/.*$/, ''))
                .filter(line => line.length > 0);
        },

        get uniqueInputCount() {
            return new Set(this.parsedInputDomains).size;
        },

        get filteredFound() {
            let list = [...this.foundDeployments];

            if (this.searchQuery.trim() !== '') {
                const q = this.searchQuery.toLowerCase().trim();
                list = list.filter(item =>
                    (item.domain && item.domain.toLowerCase().includes(q)) ||
                    (item.user && item.user.name && item.user.name.toLowerCase().includes(q)) ||
                    (item.server && item.server.name && item.server.name.toLowerCase().includes(q)) ||
                    (item.status && item.status.toLowerCase().includes(q))
                );
            }

            if (this.statusFilter !== 'all') {
                list = list.filter(item => item.status === this.statusFilter);
            }

            if (this.sortBy === 'newest') {
                list.sort((a, b) => new Date(b.created_at || 0) - new Date(a.created_at || 0));
            } else if (this.sortBy === 'oldest') {
                list.sort((a, b) => new Date(a.created_at || 0) - new Date(b.created_at || 0));
            } else if (this.sortBy === 'status') {
                list.sort((a, b) => (a.status || '').localeCompare(b.status || ''));
            } else {
                // domain (a-z)
                list.sort((a, b) => (a.domain || '').localeCompare(b.domain || ''));
            }

            return list;
        },

        get totalPages() {
            return Math.ceil(this.filteredFound.length / this.perPage) || 1;
        },

        get paginatedFound() {
            const start = (this.currentPage - 1) * this.perPage;
            return this.filteredFound.slice(start, start + this.perPage);
        },

        get allFoundSelected() {
            const deletable = this.filteredFound.filter(d => d.status !== 'processing');
            if (deletable.length === 0) return false;
            return deletable.every(d => this.selectedIds.includes(d.id));
        },

        toggleSelectAll() {
            const deletable = this.filteredFound.filter(d => d.status !== 'processing');
            if (this.allFoundSelected) {
                const deletableIds = new Set(deletable.map(d => d.id));
                this.selectedIds = this.selectedIds.filter(id => !deletableIds.has(id));
            } else {
                const toAdd = deletable.map(d => d.id);
                this.selectedIds = Array.from(new Set([...this.selectedIds, ...toAdd]));
            }
        },

        toggleSelection(id) {
            if (this.selectedIds.includes(id)) {
                this.selectedIds = this.selectedIds.filter(item => item !== id);
            } else {
                this.selectedIds.push(id);
            }
        },

        clearInput() {
            this.domainInput = '';
            this.hasSearched = false;
            this.foundDeployments = [];
            this.missingDomains = [];
            this.selectedIds = [];
            this.searchQuery = '';
            this.statusFilter = 'all';
            this.currentPage = 1;
            this.deletionSummary = null;
        },

        async searchDomains() {
            const domains = Array.from(new Set(this.parsedInputDomains));
            if (domains.length === 0) {
                window.toast?.warning('Please input at least 1 domain.');
                return;
            }

            if (domains.length > 500) {
                window.toast?.error('Maximum 500 domains per search allowed.');
                return;
            }

            this.isSearching = true;
            this.deletionSummary = null;
            try {
                const response = await bulkSearchDeployments(domains);
                const data = response?.data || {};
                this.foundDeployments = Array.isArray(data.found) ? data.found : [];
                this.missingDomains = Array.isArray(data.missing) ? data.missing : [];
                this.hasSearched = true;
                this.currentPage = 1;

                // Auto-select all found deployments that are not processing
                this.selectedIds = this.foundDeployments
                    .filter(d => d.status !== 'processing')
                    .map(d => d.id);

                if (this.foundDeployments.length === 0) {
                    window.toast?.info('No matching deployments found for the entered domains.');
                } else {
                    window.toast?.success(`Found ${this.foundDeployments.length} matching deployment(s).`);
                }
            } catch (err) {
                console.error(err);
                window.toast?.error(err.message || 'Failed to search domains.');
            } finally {
                this.isSearching = false;
            }
        },

        confirmDeleteSingle(item) {
            window.confirmModal?.show({
                title: 'Delete Deployment',
                message: `Are you sure you want to delete ${item.domain}? This will remove the web application from RunCloud and purge archive files.`,
                fields: [
                    { label: 'Domain', value: item.domain },
                    { label: 'Server', value: item.server ? item.server.name : 'Unknown' }
                ],
                confirmText: 'Delete',
                cancelText: 'Cancel',
                type: 'danger',
                onConfirm: async () => {
                    this.isDeleting = true;
                    try {
                        await bulkDeleteDeployments([item.id]);
                        this.foundDeployments = this.foundDeployments.filter(d => d.id !== item.id);
                        this.selectedIds = this.selectedIds.filter(id => id !== item.id);
                        window.toast?.success(`Deleted ${item.domain}.`);
                    } catch (err) {
                        window.toast?.error(err.message || `Failed to delete ${item.domain}.`);
                    } finally {
                        this.isDeleting = false;
                    }
                }
            });
        },

        confirmDeleteSelected() {
            const count = this.selectedIds.length;
            if (count === 0) {
                window.toast?.warning('No deployments selected for deletion.');
                return;
            }

            const domainsToDelete = this.foundDeployments
                .filter(d => this.selectedIds.includes(d.id))
                .map(d => d.domain);

            const displayList = domainsToDelete.slice(0, 5).join(', ') +
                (domainsToDelete.length > 5 ? ` and ${domainsToDelete.length - 5} more` : '');

            window.confirmModal?.show({
                title: `Delete ${count} Deployment${count > 1 ? 's' : ''}`,
                message: `Are you sure you want to permanently delete these ${count} deployment(s)? This will remove the web applications from RunCloud and purge local archives.`,
                fields: [
                    { label: 'Selected Domains', value: displayList }
                ],
                confirmText: `Delete ${count} Deployment${count > 1 ? 's' : ''}`,
                cancelText: 'Cancel',
                type: 'danger',
                onConfirm: async () => {
                    await this.performBulkDelete();
                }
            });
        },

        async performBulkDelete() {
            this.isDeleting = true;
            try {
                const response = await bulkDeleteDeployments(this.selectedIds);
                const result = response?.data || {};
                const processed = result.processed_ids || [];
                const skipped = result.skipped || [];

                this.deletionSummary = {
                    processedCount: processed.length,
                    skippedCount: skipped.length,
                    skipped: skipped
                };

                const deletedSet = new Set(processed);
                this.foundDeployments = this.foundDeployments.filter(d => !deletedSet.has(d.id));
                this.selectedIds = this.selectedIds.filter(id => !deletedSet.has(id));

                if (processed.length > 0) {
                    window.toast?.success(`Successfully deleted ${processed.length} deployment(s).`);
                }
                if (skipped.length > 0) {
                    window.toast?.warning(`${skipped.length} deployment(s) could not be deleted.`);
                }
            } catch (err) {
                console.error(err);
                window.toast?.error(err.message || 'Failed to delete deployments.');
            } finally {
                this.isDeleting = false;
            }
        },

        formatDate(dateString) {
            if (!dateString) return 'Just now';
            const date = new Date(dateString);
            return isNaN(date.getTime())
                ? 'Just now'
                : date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        },

        getInitial(name) {
            return name ? name.charAt(0).toUpperCase() : 'D';
        },

        prevPage() {
            if (this.currentPage > 1) this.currentPage--;
        },

        nextPage() {
            if (this.currentPage < this.totalPages) this.currentPage++;
        },

        goToPage(p) {
            this.currentPage = p;
        }
    };
}
