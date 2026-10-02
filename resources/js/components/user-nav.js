// resources/js/components/user-nav.js

import { fetchCurrentUser, logout } from '../services/auth-service.js';

export function userNav() {
    return {
        userName: '',
        userEmail: '',
        isLoadingUser: true,
        isLoggingOut: false,

        async init() {
            try {
                const user = await fetchCurrentUser();
                if (user) {
                    this.userName = user.name || '';
                    this.userEmail = user.email || '';
                }
            } catch (error) {
                console.error('Failed to load user profile:', error);
                if (error.status === 401) {
                    window.location.replace('/login');
                }
            } finally {
                this.isLoadingUser = false;
            }
        },

        async handleLogout() {
            this.isLoggingOut = true;

            try {
                await logout();
            } catch (error) {
                console.error('Logout failed:', error);
            } finally {
                this.isLoggingOut = false;
            }
        },
    };
}
