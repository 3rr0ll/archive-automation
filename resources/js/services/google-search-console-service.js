import { api } from './api-client.js';

const BASE_URL = '/admin/google-search-console';

export function fetchGoogleSettings() {
    return api.get(`${BASE_URL}/settings`);
}

export function updateGoogleActivation(active) {
    return api.patch(`${BASE_URL}/settings`, { active });
}

export function testGoogleConnection() {
    return api.post(`${BASE_URL}/test`);
}

export function disconnectGoogle() {
    return api.delete(`${BASE_URL}/connection`);
}

export function registerGoogleDomain(data) {
    return api.post(`${BASE_URL}/domains/register`, data);
}
