// resources/js/components/dashboard-overview.js

import { fetchCurrentUser } from '../services/auth-service.js';

export function dashboardOverview() {
    return {
        userName: '',
        isLoading: true,
        stats: {
            activeDeployments: 0,
            connectedServers: 0,
            queueWorkerStatus: 'Ready',
        },

        async init() {
            try {
                const user = await fetchCurrentUser();
                if (user) {
                    this.userName = user.name || 'User';
                }
            } catch (error) {
                console.error('Failed to load dashboard user:', error);
                if (error.status === 401) {
                    window.location.replace('/login');
                }
            } finally {
                this.isLoading = false;
            }
        },
    };
}
