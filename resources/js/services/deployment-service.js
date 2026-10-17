import api from './api-client.js';

export async function fetchDeployments() {
    return api.get('/deployments');
}

export async function fetchDeployment(id) {
    return api.get(`/deployments/${id}`);
}

export async function fetchDeploymentLogs(id, params = {}) {
    const query = new URLSearchParams(params).toString();
    return api.get(`/deployments/${id}/logs${query ? `?${query}` : ''}`);
}

export async function cancelDeployment(id) {
    return api.post(`/deployments/${id}/cancel`);
}

export async function retryDeployment(id) {
    return api.post(`/deployments/${id}/retry`);
}

export async function createDeployment(payload) {
    if (typeof FormData !== 'undefined' && payload instanceof FormData) {
        return api.post('/deployments', payload);
    }

    if (typeof Blob !== 'undefined' && payload?.archive instanceof Blob) {
        const formData = new FormData();

        Object.entries(payload).forEach(([key, value]) => {
            if (value !== null && value !== undefined && value !== '') {
                formData.append(key, value);
            }
        });

        return api.post('/deployments', formData);
    }

    return api.post('/deployments', payload);
}
