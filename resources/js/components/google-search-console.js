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

export function googleSearchConsolePage() {
    return {
        accounts: [],
        legacyFallbackConfigured: false,
        isLoading: true,
        isSaving: false,
        testingId: null,
        activatingId: null,
        showAccountForm: false,
        editingId: null,
        accountForm: emptyAccountForm(),
        isRegistering: false,
        registrationResult: null,
        domainForm: { domain: '', sitemap_url: '' },

        get hasActiveAccount() {
            return this.accounts.some((account) => account.is_active) || this.legacyFallbackConfigured;
        },

        async init() {
            await this.loadAccounts();
        },

        async loadAccounts() {
            this.isLoading = true;
            try {
                const response = await fetchGoogleAccounts();
                this.accounts = response.data || [];
                this.legacyFallbackConfigured = Boolean(response.legacy_fallback_configured);
            } catch (error) {
                window.toast?.error(error.message || 'Unable to load Google service accounts.');
            } finally {
                this.isLoading = false;
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
