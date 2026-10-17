// resources/js/services/user-service.js

import api from './api-client.js';

export async function getUsers(params = {}) {
    return await api.get('/users', { params });
}

export async function createUser(userData) {
    return await api.post('/users', userData);
}

export async function updateUser(id, userData) {
    return await api.put(`/users/${id}`, userData);
}

export async function deleteUser(id) {
    return await api.delete(`/users/${id}`);
}

export async function bulkDeleteUsers(ids) {
    return await api.post('/users/bulk-delete', { ids });
}

export async function resetUserPassword(id, passwordData) {
    return await api.put(`/users/${id}/password`, passwordData);
}
