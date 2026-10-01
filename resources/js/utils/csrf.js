/**
 * Helper to retrieve CSRF token from the meta tag.
 */
export function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}
