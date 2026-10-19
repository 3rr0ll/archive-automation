// resources/js/utils/cache.js

/**
 * Robust Client-Side Cache supporting:
 * - In-memory and optional sessionStorage/localStorage caching
 * - Configurable Time-To-Live (TTL)
 * - In-flight promise deduplication (prevent redundant simultaneous requests)
 * - Pattern-based or exact cache invalidation on mutations
 */
class ClientCache {
    constructor() {
        this.memoryStore = new Map();
        this.inFlightRequests = new Map();
        this.storagePrefix = 'app_cache_';
    }

    /**
     * Get an item from memory cache or web storage if not expired.
     */
    get(key, storage = 'memory') {
        const now = Date.now();

        // 1. Check in-memory first
        if (this.memoryStore.has(key)) {
            const entry = this.memoryStore.get(key);
            if (entry.expiresAt > now) {
                return entry.data;
            }
            this.memoryStore.delete(key);
        }

        // 2. Check persistent storage if requested
        if (storage === 'session' || storage === 'local') {
            try {
                const store = storage === 'session' ? window.sessionStorage : window.localStorage;
                const raw = store.getItem(this.storagePrefix + key);
                if (raw) {
                    const entry = JSON.parse(raw);
                    if (entry.expiresAt > now) {
                        // Re-hydrate memory store
                        this.memoryStore.set(key, entry);
                        return entry.data;
                    }
                    store.removeItem(this.storagePrefix + key);
                }
            } catch (e) {
                console.warn('[ClientCache] Failed reading from storage:', e);
            }
        }

        return null;
    }

    /**
     * Store data with a TTL in milliseconds.
     * Default TTL: 60 seconds.
     */
    set(key, data, ttlMs = 60000, storage = 'memory') {
        const expiresAt = Date.now() + ttlMs;
        const entry = { data, expiresAt };

        this.memoryStore.set(key, entry);

        if (storage === 'session' || storage === 'local') {
            try {
                const store = storage === 'session' ? window.sessionStorage : window.localStorage;
                store.setItem(this.storagePrefix + key, JSON.stringify(entry));
            } catch (e) {
                console.warn('[ClientCache] Failed writing to storage:', e);
            }
        }
    }

    /**
     * Remember / Cache wrapper: Returns cached data if available;
     * otherwise executes fetcher with automatic in-flight promise deduplication.
     */
    async remember(key, fetcher, ttlMs = 60000, storage = 'memory', forceRefresh = false) {
        if (!forceRefresh) {
            const cached = this.get(key, storage);
            if (cached !== null && cached !== undefined) {
                return cached;
            }

            // If an identical request is already running in flight, reuse its promise
            if (this.inFlightRequests.has(key)) {
                return this.inFlightRequests.get(key);
            }
        }

        const promise = (async () => {
            try {
                const data = await fetcher();
                this.set(key, data, ttlMs, storage);
                return data;
            } finally {
                this.inFlightRequests.delete(key);
            }
        })();

        this.inFlightRequests.set(key, promise);
        return promise;
    }

    /**
     * Invalidate a single key or keys matching a prefix / regex.
     */
    invalidate(keyOrPattern) {
        if (typeof keyOrPattern === 'string') {
            this.memoryStore.delete(keyOrPattern);
            this.inFlightRequests.delete(keyOrPattern);
            try {
                window.sessionStorage?.removeItem(this.storagePrefix + keyOrPattern);
                window.localStorage?.removeItem(this.storagePrefix + keyOrPattern);
            } catch { }
        }

        // Handle wildcard/prefix match (e.g. 'deployments*')
        const isPrefix = typeof keyOrPattern === 'string' && keyOrPattern.endsWith('*');
        const prefix = isPrefix ? keyOrPattern.slice(0, -1) : null;
        const isRegex = keyOrPattern instanceof RegExp;

        if (isPrefix || isRegex) {
            const shouldDelete = (k) => isPrefix ? k.startsWith(prefix) : keyOrPattern.test(k);

            for (const k of this.memoryStore.keys()) {
                if (shouldDelete(k)) this.memoryStore.delete(k);
            }
            for (const k of this.inFlightRequests.keys()) {
                if (shouldDelete(k)) this.inFlightRequests.delete(k);
            }

            try {
                ['sessionStorage', 'localStorage'].forEach(storageType => {
                    const store = window[storageType];
                    if (!store) return;
                    for (let i = store.length - 1; i >= 0; i--) {
                        const rawKey = store.key(i);
                        if (rawKey && rawKey.startsWith(this.storagePrefix)) {
                            const cleanKey = rawKey.slice(this.storagePrefix.length);
                            if (shouldDelete(cleanKey)) {
                                store.removeItem(rawKey);
                            }
                        }
                    }
                });
            } catch { }
        }
    }

    /**
     * Clear all cached items.
     */
    clear() {
        this.memoryStore.clear();
        this.inFlightRequests.clear();
        try {
            ['sessionStorage', 'localStorage'].forEach(storageType => {
                const store = window[storageType];
                if (!store) return;
                for (let i = store.length - 1; i >= 0; i--) {
                    const rawKey = store.key(i);
                    if (rawKey && rawKey.startsWith(this.storagePrefix)) {
                        store.removeItem(rawKey);
                    }
                }
            });
        } catch { }
    }
}

export const clientCache = new ClientCache();
export default clientCache;
