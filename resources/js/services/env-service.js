// resources/js/services/env-service.js

import api from './api-client.js';

export async function createEnvironmentVariable(data) {
    return await api.post('/environment-variables', data);
}

export async function updateEnvironmentVariable(id, data) {
    return await api.put(`/environment-variables/${id}`, data);
}

export async function revealEnvironmentVariable(id) {
    return await api.get(`/environment-variables/${id}/reveal`);
}

export async function deleteEnvironmentVariable(id) {
    return await api.delete(`/environment-variables/${id}`);
}
