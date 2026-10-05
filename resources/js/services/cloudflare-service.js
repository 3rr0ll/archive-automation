import api from './api-client.js';

/**
 * Change Cloudflare Worker routes for a domain:
 * - www: www.domain.com/* -> all-scripts-worker-www, domain.com/* -> domain-redirect-root-to-www
 * - nonwww: domain.com/* -> all-scripts-worker-root, www.domain.com/* -> domain-redirect-www-to-root
 *
 * @param {string} domain
 * @param {string|null} version 'www' or 'nonwww'
 */
export async function changeWorkers(domain, version = null) {
    return api.post('/cloudflare/workers', { domain, version });
}

/**
 * Configure RunCloud server DNS on Cloudflare:
 * First deletes all DNS records, then adds @ and www A records.
 *
 * @param {string} domain
 * @param {Object} options
 * @param {number|null} options.serverId
 * @param {string|null} options.serverIp
 * @param {boolean} options.proxied
 */
export async function setupRunCloudDns(domain, { serverId = null, serverIp = null, proxied = true } = {}) {
    return api.post('/cloudflare/dns/setup-runcloud', {
        domain,
        server_id: serverId,
        server_ip: serverIp,
        proxied,
    });
}

/**
 * Fetch DNS records for a domain from Cloudflare.
 *
 * @param {string} domain
 */
export async function fetchDnsRecords(domain) {
    return api.get(`/cloudflare/dns?domain=${encodeURIComponent(domain)}`);
}

/**
 * Delete all DNS records for a domain in Cloudflare.
 *
 * @param {string} domain
 */
export async function deleteAllDnsRecords(domain) {
    return api.delete('/cloudflare/dns', { body: { domain } });
}

/**
 * Purge Cloudflare cache for a domain.
 *
 * @param {string} domain
 */
export async function purgeCache(domain) {
    return api.post('/cloudflare/cache/purge', { domain });
}

export default {
    changeWorkers,
    setupRunCloudDns,
    fetchDnsRecords,
    deleteAllDnsRecords,
    purgeCache,
};
