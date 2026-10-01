// resources/js/services/api-client.js

import { getCsrfToken } from '../utils/csrf.js';

export const API_BASE_URL = '/api';

/**
 * Builds a full API URL given a path.
 * e.g. apiUrl('/login') -> '/api/login'
 *      apiUrl('deployments') -> '/api/deployments'
 */
export function apiUrl(path = '') {
    const cleanPath = path.startsWith('/') ? path : `/${path}`;
    return `${API_BASE_URL}${cleanPath}`;
}

/**
 * Returns common request headers including CSRF token and Bearer token if present.
 */
export function getApiHeaders(customHeaders = {}) {
    const token = localStorage.getItem('auth_token');
    const csrfToken = getCsrfToken();

    return {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        ...(token ? { 'Authorization': `Bearer ${token}` } : {}),
        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
        ...customHeaders,
    };
}

/**
 * Centralized fetch wrapper that handles URL construction, JSON serialization,
 * standard headers, and error normalization.
 */
export async function apiRequest(endpoint, { method = 'GET', body, headers = {}, ...options } = {}) {
    const url = apiUrl(endpoint);

    const response = await fetch(url, {
        method,
        headers: getApiHeaders(headers),
        credentials: 'same-origin',
        body: body !== undefined ? JSON.stringify(body) : undefined,
        ...options,
    });

    const isJson = response.headers.get('content-type')?.includes('application/json');
    const data = isJson ? await response.json().catch(() => ({})) : null;

    if (!response.ok) {
        const message = data?.message || `Request failed with status ${response.status}`;
        const error = new Error(message);
        error.status = response.status;
        error.errors = data?.errors || {};
        error.data = data;
        throw error;
    }

    return data;
}

export const api = {
    get: (endpoint, options) => apiRequest(endpoint, { ...options, method: 'GET' }),
    post: (endpoint, body, options) => apiRequest(endpoint, { ...options, method: 'POST', body }),
    put: (endpoint, body, options) => apiRequest(endpoint, { ...options, method: 'PUT', body }),
    patch: (endpoint, body, options) => apiRequest(endpoint, { ...options, method: 'PATCH', body }),
    delete: (endpoint, options) => apiRequest(endpoint, { ...options, method: 'DELETE' }),
};

export default api;
