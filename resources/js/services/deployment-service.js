import api from './api-client.js';
import clientCache from '../utils/cache.js';

const DEPLOYMENTS_LIST_CACHE_KEY = 'deployments:list';
const DEPLOYMENTS_TTL = 10000; // 10 seconds
const DEFAULT_ARCHIVE_CHUNK_SIZE = 1024 * 1024;

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

export async function deleteDeployment(id) {
    const res = await api.delete(`/deployments/${id}`);
    clientCache.invalidate('deployments*');
    return res;
}

export async function bulkDeleteDeployments(ids) {
    const res = await api.post('/deployments/bulk-delete', { ids });
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

export async function uploadArchiveInChunks(file, onProgress = () => {}, chunkSize = DEFAULT_ARCHIVE_CHUNK_SIZE) {
    // Never exceed 1 MB even if an older cached page/config still supplies 8 MB.
    chunkSize = Math.min(DEFAULT_ARCHIVE_CHUNK_SIZE, Math.max(256 * 1024, Number(chunkSize) || DEFAULT_ARCHIVE_CHUNK_SIZE));
    const uploadId = globalThis.crypto?.randomUUID
        ? globalThis.crypto.randomUUID()
        : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, character => {
            const random = Math.floor(Math.random() * 16);
            const value = character === 'x' ? random : ((random & 0x3) | 0x8);
            return value.toString(16);
        });
    const totalChunks = Math.ceil(file.size / chunkSize);

    for (let index = 0; index < totalChunks; index++) {
        const start = index * chunkSize;
        const chunk = file.slice(start, Math.min(start + chunkSize, file.size));
        const formData = new FormData();
        formData.append('upload_id', uploadId);
        formData.append('chunk_index', String(index));
        formData.append('total_chunks', String(totalChunks));
        formData.append('original_name', file.name);
        formData.append('total_size', String(file.size));
        formData.append('chunk', chunk, `${file.name}.part`);

        await api.post('/deployment-uploads/chunks', formData);
        onProgress(Math.round(((index + 1) / totalChunks) * 100));
    }

    const response = await api.post('/deployment-uploads/complete', { upload_id: uploadId });
    return response?.data?.upload_token || uploadId;
}
