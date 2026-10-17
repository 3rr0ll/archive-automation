// resources/js/components/users.js

import { createUser, updateUser, deleteUser, resetUserPassword } from '../services/user-service.js';

export function usersPage(initialUsers = [], currentUserId = null) {
    return {
        isLoading: false,
        items: Array.isArray(initialUsers) ? initialUsers : [],
        currentUserId: currentUserId,
        searchQuery: '',
        roleFilter: 'all',
        sortBy: 'newest',
        
        // Drawer state for Create / Edit
        showModal: false,
        modalMode: 'create', // 'create' | 'edit'
        isSubmitting: false,
        formErrors: {},
        userForm: {
            id: null,
            name: '',
            email: '',
            role: 'user',
            password: '',
            password_confirmation: '',
        },

        // Password Reset Modal state
        showPasswordModal: false,
        isResettingPassword: false,
        passwordErrors: {},
        passwordForm: {
            user_id: null,
            user_name: '',
            password: '',
            password_confirmation: '',
        },

        get filteredItems() {
            let list = [...this.items];

            if (this.searchQuery.trim() !== '') {
                const q = this.searchQuery.toLowerCase().trim();
                list = list.filter(item =>
                    (item.name && item.name.toLowerCase().includes(q)) ||
                    (item.email && item.email.toLowerCase().includes(q)) ||
                    (item.role && item.role.toLowerCase().includes(q))
                );
            }

            if (this.roleFilter === 'admin') {
                list = list.filter(item => item.role === 'admin');
            } else if (this.roleFilter === 'user') {
                list = list.filter(item => item.role === 'user');
            }

            if (this.sortBy === 'name') {
                list.sort((a, b) => (a.name || '').localeCompare(b.name || ''));
            } else if (this.sortBy === 'email') {
                list.sort((a, b) => (a.email || '').localeCompare(b.email || ''));
            } else if (this.sortBy === 'oldest') {
                list.sort((a, b) => new Date(a.created_at || 0) - new Date(b.created_at || 0));
            } else {
                // newest
                list.sort((a, b) => new Date(b.created_at || 0) - new Date(a.created_at || 0));
            }

            return list;
        },

        getInitial(name) {
            if (!name) return 'U';
            return name.trim().charAt(0).toUpperCase();
        },

        formatDate(dateString) {
            if (!dateString) return 'Never';
            const date = new Date(dateString);
            return isNaN(date.getTime())
                ? 'Never'
                : date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        },

        openCreateModal() {
            this.modalMode = 'create';
            this.formErrors = {};
            this.userForm = {
                id: null,
                name: '',
                email: '',
                role: 'user',
                password: '',
                password_confirmation: '',
            };
            this.showModal = true;
        },

        openEditModal(user) {
            this.modalMode = 'edit';
            this.formErrors = {};
            this.userForm = {
                id: user.id,
                name: user.name,
                email: user.email,
                role: user.role || 'user',
                password: '',
                password_confirmation: '',
            };
            this.showModal = true;
        },

        closeModal() {
            if (this.isSubmitting) return;
            this.showModal = false;
            this.formErrors = {};
        },

        async submitUserForm() {
            this.isSubmitting = true;
            this.formErrors = {};

            try {
                if (this.modalMode === 'create') {
                    const response = await createUser(this.userForm);
                    if (response?.data) {
                        this.items.unshift(response.data);
                    }
                    window.toast?.success('User created successfully.');
                } else {
                    const payload = {
                        name: this.userForm.name,
                        email: this.userForm.email,
                        role: this.userForm.role,
                    };
                    const response = await updateUser(this.userForm.id, payload);
                    if (response?.data) {
                        const idx = this.items.findIndex(u => u.id === this.userForm.id);
                        if (idx !== -1) {
                            this.items[idx] = { ...this.items[idx], ...response.data };
                        }
                    }
                    window.toast?.success('User profile updated.');
                }
                this.closeModal();
            } catch (error) {
                if (error.errors) {
                    this.formErrors = error.errors;
                } else {
                    window.toast?.error(error.message || 'Operation failed.');
                }
            } finally {
                this.isSubmitting = false;
            }
        },

        openPasswordModal(user) {
            this.passwordErrors = {};
            this.passwordForm = {
                user_id: user.id,
                user_name: user.name,
                password: '',
                password_confirmation: '',
            };
            this.showPasswordModal = true;
        },

        closePasswordModal() {
            if (this.isResettingPassword) return;
            this.showPasswordModal = false;
            this.passwordErrors = {};
        },

        async submitPasswordReset() {
            this.isResettingPassword = true;
            this.passwordErrors = {};

            try {
                await resetUserPassword(this.passwordForm.user_id, {
                    password: this.passwordForm.password,
                    password_confirmation: this.passwordForm.password_confirmation,
                });
                window.toast?.success('Password reset successfully.');
                this.closePasswordModal();
            } catch (error) {
                if (error.errors) {
                    this.passwordErrors = error.errors;
                } else {
                    window.toast?.error(error.message || 'Failed to reset password.');
                }
            } finally {
                this.isResettingPassword = false;
            }
        },

        deleteUserPrompt(user) {
            if (user.id === this.currentUserId) {
                window.toast?.warning('You cannot delete your own account.');
                return;
            }
            if (user.role === 'admin') {
                window.toast?.warning('Admin accounts cannot be deleted.');
                return;
            }

            if (window.confirmModal) {
                window.confirmModal.show({
                    title: 'Delete User Account',
                    message: `Are you sure you want to delete user "${user.name}"? This action cannot be undone.`,
                    key: user.email,
                    metaLabel: 'User Email',
                    confirmText: 'Delete User',
                    type: 'danger',
                    onConfirm: async () => {
                        await deleteUser(user.id);
                        this.items = this.items.filter(u => u.id !== user.id);
                        window.toast?.success('User deleted successfully.');
                    }
                });
            } else if (confirm(`Are you sure you want to delete user "${user.name}"?`)) {
                deleteUser(user.id).then(() => {
                    this.items = this.items.filter(u => u.id !== user.id);
                    window.toast?.success('User deleted successfully.');
                }).catch(err => {
                    window.toast?.error(err.message || 'Failed to delete user.');
                });
            }
        }
    };
}
