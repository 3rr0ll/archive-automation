// resources/js/services/server-service.js

import api from './api-client.js';

export async function getServers() {
    return await api.get('/servers');
}

export async function syncServers() {
    return await api.post('/servers/sync');
}

export async function toggleServerActive(id, isActive = null) {
    return await api.patch(`/servers/${id}/toggle-active`, {
        is_active: isActive,
    });
}
