<div x-show="deepCheckDomains.length > 0" x-cloak class="space-y-6">
    <template x-if="failedDomains.length > 0">
        <div class="bg-white dark:bg-[#18181b] border border-red-200 dark:border-red-900/60 rounded-lg p-5 transition-colors">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-xs font-semibold text-red-700 dark:text-red-400 uppercase tracking-wider flex items-center gap-2">
                    <x-lucide-alert-triangle class="size-4" />
                    <span>Check Failed</span>
                    <span class="text-xs font-normal normal-case" x-text="`(${failedDomains.length})`"></span>
                </h3>
            </div>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mb-3">
                RunCloud did not answer for these domains, so they may still exist on a selected server:
            </p>
            <div class="flex flex-wrap gap-2">
                <template x-for="(dom, idx) in failedDomains" :key="idx">
                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-mono bg-red-50 dark:bg-red-950/40 text-red-700 dark:text-red-300 border border-red-200 dark:border-red-900/60"
                          x-text="dom"></span>
                </template>
            </div>
        </div>
    </template>

    <template x-if="missingDomains.length > 0">
        <div class="bg-white dark:bg-[#18181b] border border-zinc-200 dark:border-zinc-800 rounded-lg p-5 transition-colors">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-xs font-semibold text-zinc-900 dark:text-zinc-100 uppercase tracking-wider flex items-center gap-2">
                    <x-lucide-alert-circle class="size-4 text-zinc-400 dark:text-zinc-500" />
                    <span>Domains Not Found</span>
                    <span class="text-xs font-normal normal-case text-zinc-500 dark:text-zinc-400" x-text="`(${missingDomains.length})`"></span>
                </h3>
            </div>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mb-3">
                These domains were not found on the selected servers. An app named differently from its domain can be missed, and Deep Check will find it.
            </p>
            <div class="flex flex-wrap gap-2">
                <template x-for="(dom, idx) in missingDomains" :key="idx">
                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-mono bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700"
                          x-text="dom"></span>
                </template>
            </div>
        </div>
    </template>

    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-end gap-3">
        <span class="text-xs text-zinc-500 dark:text-zinc-400" x-text="`Uses at least ${deepCheckMinRequests} RunCloud requests`"></span>
        <button type="button"
                @click="deepCheck()"
                :disabled="isSearching || isDeleting"
                class="inline-flex items-center justify-center gap-2 px-4 py-2 border border-zinc-200 dark:border-zinc-700 text-sm font-medium rounded-lg text-zinc-900 dark:text-zinc-100 bg-white dark:bg-[#121214] hover:bg-zinc-50 dark:hover:bg-zinc-800 disabled:opacity-50 focus:outline-none transition-colors cursor-pointer">
            <x-lucide-search class="size-4" />
            <span x-text="`Deep Check ${deepCheckDomains.length} Domain${deepCheckDomains.length === 1 ? '' : 's'}`"></span>
        </button>
    </div>
</div>
