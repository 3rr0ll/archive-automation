import api from './api-client.js';

export async function fetchDeployments() {
    return api.get('/deployments');
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
