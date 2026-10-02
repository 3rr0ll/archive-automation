// resources/js/services/auth-service.js

import api from './api-client.js';

/**
 * Log in with email and password.
 * The server sets the secure HttpOnly session cookie automatically.
 */
export async function login({ email, password, remember = false }) {
    // Clean up any legacy localStorage token
    localStorage.removeItem('auth_token');
    localStorage.removeItem('auth_token_issued_at');

    return api.post('/login', {
        email,
        password,
        remember,
    });
}

let currentUserPromise = null;

/**
 * Fetch the currently authenticated user's profile using the HttpOnly session cookie.
 * In-flight requests are deduplicated so simultaneous component calls share one request.
 */
export async function fetchCurrentUser(forceRefresh = false) {
    if (!currentUserPromise || forceRefresh) {
        currentUserPromise = api.get('/user').catch((error) => {
            currentUserPromise = null;
            throw error;
        });
    }
    return currentUserPromise;
}

/**
 * Log out from the API and destroy the server session.
 */
export async function logout() {
    try {
        await api.post('/logout');
    } finally {
        currentUserPromise = null;
        localStorage.removeItem('auth_token');
        localStorage.removeItem('auth_token_issued_at');
        window.location.replace('/login');
    }
}
