import { fetchDeployments } from '../services/deployment-service.js';

export function deploymentTable() {
    return {
        deployments: [],
        isLoading: false,
        errorMessage: '',

        async loadDeployments() {
            this.isLoading = true;
            this.errorMessage = '';

            try {
                const result = await fetchDeployments();
                this.deployments = result.data ?? [];
            } catch (error) {
                this.errorMessage = error.message || 'Something went wrong.';
            } finally {
                this.isLoading = false;
            }
        },
    };
}
