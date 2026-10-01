const BASE_URL = '/api/deployments';

export async function fetchDeployments() {
    const response = await fetch(BASE_URL, {
        headers: {
            Accept: 'application/json',
        },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw new Error('Unable to load deployments.');
    }

    return response.json();
}

export async function createDeployment(payload) {
    const response = await fetch(BASE_URL, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content') ?? '',
        },
        credentials: 'same-origin',
        body: JSON.stringify(payload),
    });

    if (!response.ok) {
        throw new Error('Unable to create deployment.');
    }

    return response.json();
}
