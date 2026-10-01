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
        fieldErrors: {},

        async submit() {
            this.isLoading = true;
            this.errorMessage = '';
            this.fieldErrors = {};

            try {
                await login({
                    email: this.email,
                    password: this.password,
                });

                // Redirect to dashboard on success
                window.location.href = '/dashboard';
            } catch (error) {
                this.errorMessage = error.message || 'Sign in failed. Please check your credentials.';
                if (error.errors) {
                    this.fieldErrors = error.errors;
                }
            } finally {
                this.isLoading = false;
            }
        },
    };
}
