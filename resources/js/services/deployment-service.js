import api from './api-client.js';
import clientCache from '../utils/cache.js';

const DEPLOYMENTS_LIST_CACHE_KEY = 'deployments:list';
const DEPLOYMENTS_TTL = 10000; // 10 seconds

export async function fetchDeployments(forceRefresh = false) {
    return clientCache.remember(
        DEPLOYMENTS_LIST_CACHE_KEY,
        () => api.get('/deployments'),
        DEPLOYMENTS_TTL,
        'memory',
        forceRefresh
    );
}

export async function fetchDeployment(id, forceRefresh = false) {
    return clientCache.remember(
        `deployments:${id}`,
        () => api.get(`/deployments/${id}`),
        DEPLOYMENTS_TTL,
        'memory',
        forceRefresh
    );
}

export async function fetchDeploymentLogs(id, params = {}) {
    const query = new URLSearchParams(params).toString();
    return api.get(`/deployments/${id}/logs${query ? `?${query}` : ''}`);
}

export async function cancelDeployment(id) {
    const res = await api.post(`/deployments/${id}/cancel`);
    clientCache.invalidate('deployments*');
    return res;
}

export async function retryDeployment(id) {
    const res = await api.post(`/deployments/${id}/retry`);
    clientCache.invalidate('deployments*');
    return res;
}

export async function createDeployment(payload) {
    let result;
    if (typeof FormData !== 'undefined' && payload instanceof FormData) {
        result = await api.post('/deployments', payload);
    } else if (typeof Blob !== 'undefined' && payload?.archive instanceof Blob) {
        const formData = new FormData();

        Object.entries(payload).forEach(([key, value]) => {
            if (value !== null && value !== undefined && value !== '') {
                formData.append(key, value);
            }
        });

        result = await api.post('/deployments', formData);
    } else {
        result = await api.post('/deployments', payload);
    }

    // Invalidate deployments cache on newly created deployment
    clientCache.invalidate('deployments*');
    return result;
}
