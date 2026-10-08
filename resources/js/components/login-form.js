// resources/js/components/login-form.js

import { login } from '../services/auth-service.js';

export function loginForm() {
    return {
        // Form inputs
        email: '',
        password: '',

        // UI states (per README requirements)
        isLoading: false,
        errorMessage: '',
        emailError: '',
        fieldErrors: {},
        showPassword: false,

        validateEmail() {
            const trimmed = this.email.trim();
            if (!trimmed) {
                this.emailError = '';
                return false;
            }

            if (!trimmed.includes('@')) {
                this.emailError = "Please include an '@' in the email address.";
                return false;
            }

            const parts = trimmed.split('@');
            if (!parts[1] || !parts[1].includes('.')) {
                this.emailError = "Please enter a valid email address (e.g. name@example.com).";
                return false;
            }

            this.emailError = '';
            return true;
        },

        async submit() {
            if (!this.email?.trim() || !this.password) return;

            if (!this.validateEmail()) return;

            this.isLoading = true;
            this.errorMessage = '';
            this.fieldErrors = {};

            try {
                await login({
                    email: this.email,
                    password: this.password,
                });

                // Use replace so login is removed from browser history (Back button won't return to login)
                window.location.replace('/dashboard');
            } catch (error) {
                this.errorMessage = error.message || 'Sign in failed. Please check your credentials.';
                if (error.errors) {
                    this.fieldErrors = error.errors;
                    if (error.errors.email) {
                        this.emailError = error.errors.email[0];
                    }
                }
            } finally {
                this.isLoading = false;
            }
        },
    };
}
