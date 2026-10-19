// resources/js/services/server-service.js

import api from './api-client.js';
import clientCache from '../utils/cache.js';

const SERVERS_CACHE_KEY = 'servers:list';
const SERVERS_TTL = 30000; // 30 seconds (matches server-side cache intent)

export async function getServers(forceRefresh = false) {
    return await clientCache.remember(
        SERVERS_CACHE_KEY,
        () => api.get('/servers'),
        SERVERS_TTL,
        'memory',
        forceRefresh
    );
}

export async function syncServers() {
    const res = await api.post('/servers/sync');
    clientCache.invalidate(SERVERS_CACHE_KEY);
    return res;
}

export async function toggleServerActive(id, isActive = null) {
    const res = await api.patch(`/servers/${id}/toggle-active`, {
        is_active: isActive,
    });
    clientCache.invalidate(SERVERS_CACHE_KEY);
    return res;
}
