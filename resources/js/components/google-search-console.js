import {
    disconnectGoogle,
    fetchGoogleSettings,
    registerGoogleDomain,
    testGoogleConnection,
    updateGoogleActivation,
} from '../services/google-search-console-service.js';

export function googleSearchConsolePage() {
    return {
        settings: {
            client_configured: false,
            connected: false,
            active: false,
            account_email: null,
            redirect_uri: null,
        },
        isLoading: true,
        isUpdating: false,
        isTesting: false,
        isRegistering: false,
        registrationResult: null,
        domainForm: {
            domain: '',
            sitemap_url: '',
        },

        async init() {
            try {
                const response = await fetchGoogleSettings();
                this.settings = response.data;
            } catch (error) {
                window.toast?.error(error.message || 'Unable to load Google settings.');
            } finally {
                this.isLoading = false;
            }
        },

        async toggleActive() {
            this.isUpdating = true;
            try {
                const response = await updateGoogleActivation(!this.settings.active);
                this.settings = response.data;
                window.toast?.success(response.message);
            } catch (error) {
                window.toast?.error(error.message || 'Unable to update Google automation.');
            } finally {
                this.isUpdating = false;
            }
        },

        async testConnection() {
            this.isTesting = true;
            try {
                const response = await testGoogleConnection();
                window.toast?.success(`${response.message} ${response.data.accessible_properties} properties accessible.`);
            } catch (error) {
                window.toast?.error(error.message || 'Google Search Console connection failed.');
            } finally {
                this.isTesting = false;
            }
        },

        disconnect() {
            const remove = async () => {
                try {
                    const response = await disconnectGoogle();
                    this.settings = response.data;
                    window.toast?.success(response.message);
                } catch (error) {
                    window.toast?.error(error.message || 'Unable to disconnect Google.');
                }
            };

            if (window.confirmModal) {
                window.confirmModal.show({
                    title: 'Disconnect Google Search Console',
                    message: 'This removes the encrypted OAuth refresh token and disables automatic Search Console actions.',
                    key: this.settings.account_email || 'Google connection',
                    type: 'danger',
                    confirmText: 'Disconnect',
                    cancelText: 'Cancel',
                    onConfirm: remove,
                });
                return;
            }

            if (window.confirm('Disconnect Google Search Console?')) remove();
        },

        async registerDomain() {
            this.isRegistering = true;
            this.registrationResult = null;
            try {
                const payload = { domain: this.domainForm.domain.trim() };
                if (this.domainForm.sitemap_url.trim()) {
                    payload.sitemap_url = this.domainForm.sitemap_url.trim();
                }

                const response = await registerGoogleDomain(payload);
                this.registrationResult = { success: true, message: response.message };
                window.toast?.success(response.message);
            } catch (error) {
                this.registrationResult = {
                    success: false,
                    message: error.message || 'Unable to register the domain.',
                };
                window.toast?.error(this.registrationResult.message);
            } finally {
                this.isRegistering = false;
            }
        },
    };
}
