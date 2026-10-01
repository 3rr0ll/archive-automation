// resources/js/services/auth-service.js

import api from './api-client.js';

const TOKEN_KEY = 'auth_token';
const TOKEN_ISSUED_AT_KEY = 'auth_token_issued_at';

// Refresh threshold: 30 minutes (1,800,000 ms)
const REFRESH_THRESHOLD_MS = 30 * 60 * 1000;

/**
 * Persist the token and issue timestamp in localStorage.
 */
function storeToken(token) {
    localStorage.setItem(TOKEN_KEY, token);
    localStorage.setItem(TOKEN_ISSUED_AT_KEY, Date.now().toString());
}

/**
 * Remove token and timestamp from localStorage.
 */
function clearToken() {
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(TOKEN_ISSUED_AT_KEY);
}

/**
 * Returns true if the token is older than the 30-minute refresh threshold.
 */
function isTokenStale() {
    const issuedAt = localStorage.getItem(TOKEN_ISSUED_AT_KEY);
    if (!issuedAt) {
        return false;
    }
    return (Date.now() - Number(issuedAt)) >= REFRESH_THRESHOLD_MS;
}

/**
 * Log in with email and password, storing the returned Sanctum token.
 */
export async function login({ email, password, deviceName = 'web-browser' }) {
    const data = await api.post('/login', {
        email,
        password,
        device_name: deviceName,
    });

    if (data.access_token) {
        storeToken(data.access_token);
    }

    return data;
}

/**
 * Fetch the currently authenticated user's profile.
 * Automatically refreshes the token if it has passed the 30-minute threshold.
 */
export async function fetchCurrentUser() {
    if (hasToken() && isTokenStale()) {
        try {
            await refreshToken();
        } catch {
            // Continue to user call
        }
    }

    try {
        return await api.get('/user');
    } catch (error) {
        clearToken();
        throw error;
    }
}

/**
 * Log out from the API and clear stored credentials.
 */
export async function logout() {
    try {
        await api.post('/logout');
    } finally {
        clearToken();
        window.location.href = '/login';
    }
}

/**
 * Refresh the access token and store the new token in localStorage.
 */
export async function refreshToken() {
    try {
        const data = await api.post('/refresh');

        if (data.access_token) {
            storeToken(data.access_token);
        }

        return data;
    } catch (error) {
        clearToken();
        throw error;
    }
}

/**
 * Check if a token currently exists in storage.
 */
export function hasToken() {
    return Boolean(localStorage.getItem(TOKEN_KEY));
}

/**
 * Initializes a background refresh watcher that checks every minute.
 * If the active token is 30 minutes old or older, it automatically refreshes it.
 */
export function initTokenRefreshWatcher() {
    const CHECK_INTERVAL_MS = 60 * 1000;

    setInterval(async () => {
        if (hasToken() && isTokenStale()) {
            try {
                await refreshToken();
            } catch (error) {
                console.warn('Silent token refresh failed:', error.message);
                window.location.href = '/login';
            }
        }
    }, CHECK_INTERVAL_MS);
}


