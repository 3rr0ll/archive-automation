import { syncServers, toggleServerActive } from '../services/server-service.js';

export function serversPage(initialServers = []) {
    return {
        items: Array.isArray(initialServers) ? initialServers : [],
        isSyncing: false,
        searchQuery: '',
        statusFilter: 'all',
        sortBy: 'newest',
        togglingId: null,

        get filteredItems() {
            let list = [...this.items];

            if (this.searchQuery.trim() !== '') {
                const q = this.searchQuery.toLowerCase().trim();
                list = list.filter(item =>
                    (item.name && item.name.toLowerCase().includes(q)) ||
                    (String(item.server_id).toLowerCase().includes(q)) ||
                    (item.ip_address && item.ip_address.toLowerCase().includes(q))
                );
            }

            if (this.statusFilter === 'active') {
                list = list.filter(item => Boolean(item.is_active));
            } else if (this.statusFilter === 'inactive') {
                list = list.filter(item => !Boolean(item.is_active));
            }

            if (this.sortBy === 'name') {
                list.sort((a, b) => (a.name || '').localeCompare(b.name || ''));
            } else if (this.sortBy === 'server_id') {
                list.sort((a, b) => Number(a.server_id) - Number(b.server_id));
            } else {
                // newest
                list.sort((a, b) => new Date(b.created_at || 0) - new Date(a.created_at || 0));
            }

            return list;
        },

        formatDate(dateString) {
            if (!dateString) return 'Just now';
            const date = new Date(dateString);
            return isNaN(date.getTime())
                ? 'Just now'
                : date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        },

        getInitial(item) {
            if (item?.name) {
                return item.name.charAt(0).toUpperCase();
            }
            return 'S';
        },

        async syncFromRunCloud() {
            if (this.isSyncing) return;
            this.isSyncing = true;

            try {
                const response = await syncServers();
                const syncedData = response?.data || response;

                if (Array.isArray(syncedData)) {
                    this.items = syncedData;
                } else if (syncedData?.data && Array.isArray(syncedData.data)) {
                    this.items = syncedData.data;
                }

                if (window.toast) {
                    window.toast.success('Servers synced successfully from RunCloud.');
                }
            } catch (error) {
                console.error('Failed to sync servers:', error);
                window.toast?.error(error.message || 'Failed to sync servers from RunCloud.');
            } finally {
                this.isSyncing = false;
            }
        },

        async toggleActive(item) {
            if (this.togglingId === item.id) return;
            this.togglingId = item.id;
            const targetStatus = !item.is_active;

            try {
                const response = await toggleServerActive(item.id, targetStatus);
                const updated = response?.data || response;

                const idx = this.items.findIndex(i => i.id === item.id);
                if (idx !== -1) {
                    this.items[idx].is_active = updated.is_active !== undefined ? updated.is_active : targetStatus;
                }
                window.toast?.success(`Server marked as ${targetStatus ? 'Active' : 'Inactive'}.`);
            } catch (error) {
                console.error('Failed to update server status:', error);
                window.toast?.error(error.message || 'Failed to update server status.');
            } finally {
                this.togglingId = null;
            }
        },

        copyText(text, label = 'Copied') {
            if (navigator?.clipboard) {
                navigator.clipboard.writeText(text);
                window.toast?.success(`${label} copied to clipboard!`);
            }
        }
    };
}
