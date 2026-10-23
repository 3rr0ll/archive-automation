import {
    activateGoogleAccount,
    createGoogleAccount,
    deleteGoogleAccount,
    fetchGoogleAccounts,
    registerGoogleDomain,
    testGoogleAccount,
    updateGoogleAccount,
} from '../services/google-search-console-service.js';

const emptyAccountForm = () => ({
    name: '',
    owner_email: '',
    credentials_json: '',
    is_active: false,
});

export function googleSearchConsolePage(initialAccounts = [], initialLegacyFallback = false) {
    return {
        accounts: Array.isArray(initialAccounts) ? initialAccounts : [],
        legacyFallbackConfigured: Boolean(initialLegacyFallback),
        isLoading: false,
        isSaving: false,
        testingId: null,
        activatingId: null,
        showAccountForm: false,
        editingId: null,
        accountForm: emptyAccountForm(),
        searchQuery: '',
        statusFilter: 'all',
        sortBy: 'newest',
        oauthWindow: null,
        oauthMessageHandler: null,

        get filteredAccounts() {
            let list = [...this.accounts];

            if (this.searchQuery.trim() !== '') {
                const q = this.searchQuery.toLowerCase().trim();
                list = list.filter(item =>
                    (item.name && item.name.toLowerCase().includes(q)) ||
                    (item.client_email && item.client_email.toLowerCase().includes(q)) ||
                    (item.owner_email && item.owner_email.toLowerCase().includes(q))
                );
            }

            if (this.statusFilter === 'active') {
                list = list.filter(item => Boolean(item.is_active));
            } else if (this.statusFilter === 'inactive') {
                list = list.filter(item => !Boolean(item.is_active));
            }

            if (this.sortBy === 'name') {
                list.sort((a, b) => (a.name || '').localeCompare(b.name || ''));
            } else if (this.sortBy === 'email') {
                list.sort((a, b) => (a.client_email || '').localeCompare(b.client_email || ''));
            } else {
                // newest
                list.sort((a, b) => new Date(b.created_at || 0) - new Date(a.created_at || 0));
            }

            return list;
        },

        get hasActiveAccount() {
            return this.accounts.some((account) => account.is_active) || this.legacyFallbackConfigured;
        },

        formatDate(dateString) {
            if (!dateString) return 'Just now';
            const date = new Date(dateString);
            return isNaN(date.getTime())
                ? 'Just now'
                : date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        },

        async init() {
            if (this.oauthMessageHandler) {
                return;
            }

            this.oauthMessageHandler = async (event) => {
                if (event.origin !== window.location.origin || event.data?.type !== 'google-oauth-complete') {
                    return;
                }

                this.oauthWindow = null;

                if (event.data.success) {
                    window.location.reload();
                    return;
                }

                window.toast?.error(event.data.message || 'Google authorization failed.');
            };
            window.addEventListener('message', this.oauthMessageHandler);

            if (!this.accounts || this.accounts.length === 0) {
                await this.loadAccounts();
            }
        },

        destroy() {
            if (this.oauthMessageHandler) {
                window.removeEventListener('message', this.oauthMessageHandler);
            }
        },

        connectGoogle(oauthUrl) {
            this.oauthWindow = window.open(oauthUrl, 'google-oauth-connect');

            if (!this.oauthWindow) {
                window.toast?.error('Your browser blocked the Google sign-in tab. Please allow pop-ups for this site and try again.');
                return;
            }

            this.oauthWindow.focus();
        },

        async loadAccounts(showLoader = true) {
            if (showLoader) this.isLoading = true;
            try {
                const response = await fetchGoogleAccounts();
                this.accounts = response.data || [];
                this.legacyFallbackConfigured = Boolean(response.legacy_fallback_configured);
            } catch (error) {
                window.toast?.error(error.message || 'Unable to load Google service accounts.');
            } finally {
                if (showLoader) this.isLoading = false;
            }
        },

        openCreateForm() {
            this.editingId = null;
            this.accountForm = emptyAccountForm();
            this.showAccountForm = true;
        },

        openEditForm(account) {
            this.editingId = account.id;
            this.accountForm = {
                name: account.name,
                owner_email: account.owner_email || '',
                credentials_json: '',
                is_active: account.is_active,
            };
            this.showAccountForm = true;
        },

        closeAccountForm() {
            this.showAccountForm = false;
            this.editingId = null;
            this.accountForm = emptyAccountForm();
        },

        async saveAccount() {
            this.isSaving = true;
            try {
                const payload = {
                    name: this.accountForm.name.trim(),
                    owner_email: this.accountForm.owner_email.trim() || null,
                    is_active: Boolean(this.accountForm.is_active),
                };
                if (this.accountForm.credentials_json.trim()) {
                    payload.credentials_json = this.accountForm.credentials_json.trim();
                }

                const response = this.editingId
                    ? await updateGoogleAccount(this.editingId, payload)
                    : await createGoogleAccount(payload);
                window.toast?.success(response.message);
                this.closeAccountForm();
                await this.loadAccounts();
            } catch (error) {
                const validationMessage = Object.values(error.errors || {})?.[0]?.[0];
                window.toast?.error(validationMessage || error.message || 'Unable to save Google service account.');
            } finally {
                this.isSaving = false;
            }
        },

        async activateAccount(account) {
            this.activatingId = account.id;
            try {
                const response = await activateGoogleAccount(account.id);
                window.toast?.success(response.message);
                await this.loadAccounts();
            } catch (error) {
                window.toast?.error(error.message || 'Unable to activate the account.');
            } finally {
                this.activatingId = null;
            }
        },

        async testAccount(account) {
            this.testingId = account.id;
            try {
                const response = await testGoogleAccount(account.id);
                window.toast?.success(`${response.message} ${response.data.accessible_properties} properties accessible.`);
            } catch (error) {
                window.toast?.error(error.message || 'Google Search Console connection failed.');
            } finally {
                this.testingId = null;
            }
        },

        deleteAccount(account) {
            const remove = async () => {
                try {
                    const response = await deleteGoogleAccount(account.id);
                    window.toast?.success(response.message);
                    await this.loadAccounts();
                } catch (error) {
                    window.toast?.error(error.message || 'Unable to delete the account.');
                }
            };

            if (window.confirmModal) {
                window.confirmModal.show({
                    title: 'Delete Google service account',
                    message: 'The encrypted JSON key will be permanently removed.',
                    key: account.client_email,
                    type: 'danger',
                    confirmText: 'Delete',
                    cancelText: 'Cancel',
                    onConfirm: remove,
                });
                return;
            }

            if (window.confirm(`Delete ${account.client_email}?`)) remove();
        },

        async registerDomain() {
            this.isRegistering = true;
            this.registrationResult = null;
            try {
                const payload = { domain: this.domainForm.domain.trim() };
                if (this.domainForm.sitemap_url.trim()) payload.sitemap_url = this.domainForm.sitemap_url.trim();
                const response = await registerGoogleDomain(payload);
                this.registrationResult = { success: true, message: response.message };
                window.toast?.success(response.message);
            } catch (error) {
                this.registrationResult = { success: false, message: error.message || 'Unable to register the domain.' };
                window.toast?.error(this.registrationResult.message);
            } finally {
                this.isRegistering = false;
            }
        },
    };
}
