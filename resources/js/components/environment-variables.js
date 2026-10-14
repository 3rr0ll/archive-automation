import { createEnvironmentVariable, updateEnvironmentVariable, deleteEnvironmentVariable } from '../services/env-service.js';

export function environmentVariablesPage(initialVariables = []) {
    return {
        items: Array.isArray(initialVariables) ? initialVariables : [],
        showModal: false,
        isSubmitting: false,
        type: 'secret',
        variables: [
            { key: '', value: '', showValue: false }
        ],
        errors: {},
        editingId: null,
        editForm: {
            id: null,
            key: '',
            value: '',
            note: '',
            type: 'config',
            environments: 'All Environments',
            isSaving: false,
            errors: {},
        },

        formatDate(dateString) {
            if (!dateString) return 'Just now';
            const date = new Date(dateString);
            return isNaN(date.getTime())
                ? 'Just now'
                : date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        },

        getInitial(item) {
            if (item?.user?.name) {
                return item.user.name.charAt(0).toUpperCase();
            }
            return 'U';
        },

        openModal() {
            this.showModal = true;
            this.type = 'secret';
            this.errors = {};
            this.variables = [
                { key: '', value: '', showValue: false }
            ];
        },

        closeModal() {
            this.showModal = false;
            this.errors = {};
        },

        addVariableField() {
            this.variables.push({
                key: '',
                value: '',
                showValue: false
            });
        },

        removeVariableField(index) {
            if (this.variables.length > 1) {
                this.variables.splice(index, 1);
            }
        },

        toggleShowValue(index) {
            this.variables[index].showValue = !this.variables[index].showValue;
        },

        async submitForm() {
            this.isSubmitting = true;
            this.errors = {};

            const validItems = this.variables.filter(v => v.key.trim() !== '' || v.value.trim() !== '');
            if (validItems.length === 0) {
                this.errors = { key: ['Please enter at least one variable key and value.'] };
                this.isSubmitting = false;
                return;
            }

            try {
                for (let i = 0; i < validItems.length; i++) {
                    const item = validItems[i];
                    const created = await createEnvironmentVariable({
                        type: this.type,
                        key: item.key.trim(),
                        value: item.value,
                        environments: 'Production'
                    });

                    // Add new item to local array immediately without reloading page
                    if (created && created.id) {
                        this.items.unshift(created);
                    }
                }

                this.closeModal();
            } catch (error) {
                if (error.status === 422) {
                    this.errors = error.errors || {};
                } else {
                    alert(error.message || 'Something went wrong while saving environment variables.');
                }
            } finally {
                this.isSubmitting = false;
            }
        },

        async deleteVariable(id) {
            const item = this.items.find(i => i.id === id);
            const keyName = item ? item.key : 'this variable';

            if (window.confirmModal) {
                window.confirmModal.show({
                    title: 'Delete Environment Variable',
                    message: "This will permanently delete the environment variable. You can't undo this action.",
                    key: keyName,
                    type: 'danger',
                    confirmText: 'Delete',
                    cancelText: 'Cancel',
                    onConfirm: async () => {
                        try {
                            await deleteEnvironmentVariable(id);
                            this.items = this.items.filter(item => item.id !== id);
                        } catch (error) {
                            alert(error.message || 'Failed to delete');
                        }
                    }
                });
                return;
            }

            // Fallback if modal not present
            if (confirm('Are you sure you want to delete this?')) {
                try {
                    await deleteEnvironmentVariable(id);
                    this.items = this.items.filter(item => item.id !== id);
                } catch (error) {
                    alert(error.message || 'Failed to delete');
                }
            }
        },

        startEdit(item) {
            this.editingId = item.id;
            this.editForm = {
                id: item.id,
                key: item.key || '',
                value: item.value || '',
                note: item.note || '',
                type: item.type || 'config',
                environments: item.environments || 'All Environments',
                isSaving: false,
                errors: {},
            };
        },

        cancelEdit() {
            this.editingId = null;
            this.editForm = {
                id: null,
                key: '',
                value: '',
                note: '',
                type: 'config',
                environments: 'All Environments',
                isSaving: false,
                errors: {},
            };
        },

        async saveEdit() {
            if (!this.editForm.key.trim()) {
                this.editForm.errors = { key: ['Key is required'] };
                return;
            }

            this.editForm.isSaving = true;
            this.editForm.errors = {};

            try {
                const updated = await updateEnvironmentVariable(this.editForm.id, {
                    key: this.editForm.key.trim(),
                    value: this.editForm.value,
                    type: this.editForm.type,
                    note: this.editForm.note,
                    environments: this.editForm.environments,
                });

                // Update in local items list
                const idx = this.items.findIndex(i => i.id === this.editForm.id);
                if (idx !== -1) {
                    this.items[idx] = {
                        ...this.items[idx],
                        ...updated,
                    };
                }

                this.cancelEdit();
            } catch (error) {
                if (error.status === 422) {
                    this.editForm.errors = error.errors || {};
                } else {
                    alert(error.message || 'Failed to update environment variable');
                }
            } finally {
                this.editForm.isSaving = false;
            }
        },

        copyKey(name) {
            if (navigator?.clipboard) {
                navigator.clipboard.writeText(name);
                alert('Copied to clipboard!');
            }
        }
    };
}

