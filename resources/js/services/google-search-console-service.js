import { api } from './api-client.js';

const BASE_URL = '/admin/google-search-console';

export function fetchGoogleAccounts() {
    return api.get(`${BASE_URL}/accounts`);
}

export function createGoogleAccount(data) {
    return api.post(`${BASE_URL}/accounts`, data);
}

export function updateGoogleAccount(id, data) {
    return api.patch(`${BASE_URL}/accounts/${id}`, data);
}

export function activateGoogleAccount(id) {
    return api.patch(`${BASE_URL}/accounts/${id}/activate`, {});
}

export function testGoogleAccount(id) {
    return api.post(`${BASE_URL}/accounts/${id}/test`);
}

export function deleteGoogleAccount(id) {
    return api.delete(`${BASE_URL}/accounts/${id}`);
}

export function registerGoogleDomain(data) {
    return api.post(`${BASE_URL}/domains/register`, data);
}
