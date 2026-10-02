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
        showPassword: false,


        async submit() {
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
                }
            } finally {
                this.isLoading = false;
            }
        },
    };
}
