// resources/js/services/user-service.js

import api from './api-client.js';
import clientCache from '../utils/cache.js';

const USERS_CACHE_PREFIX = 'users:';

export async function getUsers(params = {}, forceRefresh = false) {
    const key = `${USERS_CACHE_PREFIX}${JSON.stringify(params)}`;
    return await clientCache.remember(
        key,
        () => api.get('/users', { params }),
        30000, // 30 seconds
        'memory',
        forceRefresh
    );
}

export async function createUser(userData) {
    const res = await api.post('/users', userData);
    clientCache.invalidate(`${USERS_CACHE_PREFIX}*`);
    return res;
}

export async function updateUser(id, userData) {
    const res = await api.put(`/users/${id}`, userData);
    clientCache.invalidate(`${USERS_CACHE_PREFIX}*`);
    return res;
}

export async function deleteUser(id) {
    const res = await api.delete(`/users/${id}`);
    clientCache.invalidate(`${USERS_CACHE_PREFIX}*`);
    return res;
}

export async function bulkDeleteUsers(ids) {
    const res = await api.post('/users/bulk-delete', { ids });
    clientCache.invalidate(`${USERS_CACHE_PREFIX}*`);
    return res;
}

export async function resetUserPassword(id, passwordData) {
    return await api.put(`/users/${id}/password`, passwordData);
}
