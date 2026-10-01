import api from './api-client.js';

export async function fetchDeployments() {
    return api.get('/deployments');
}

export async function createDeployment(payload) {
    return api.post('/deployments', payload);
}

