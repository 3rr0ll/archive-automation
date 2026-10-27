<div class="bg-white dark:bg-[#18181b] border border-zinc-200 dark:border-zinc-800 rounded-lg p-5 transition-colors">
    <div class="flex items-center justify-between mb-3">
        <label for="bulk-domain-input" class="text-xs font-semibold text-zinc-900 dark:text-zinc-100 uppercase tracking-wider flex items-center gap-2">
            <span>Input Domains</span>
            <span class="text-[11px] font-normal normal-case text-zinc-500 dark:text-zinc-400">(one per line)</span>
        </label>
        <div class="flex items-center gap-3">
            <span class="text-xs text-zinc-500 dark:text-zinc-400" x-show="parsedInputDomains.length > 0">
                <span class="font-medium text-zinc-900 dark:text-zinc-100" x-text="parsedInputDomains.length"></span> line<span x-show="parsedInputDomains.length !== 1">s</span>
                (<span class="font-medium text-zinc-900 dark:text-zinc-100" x-text="uniqueInputCount"></span> unique)
            </span>
            <button type="button"
                    x-show="domainInput.trim().length > 0"
                    @click="clearInput()"
                    class="text-xs text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 transition cursor-pointer">
                Clear
            </button>
        </div>
    </div>

    <textarea id="bulk-domain-input"
              x-model="domainInput"
              rows="7"
              :disabled="isSearching || isDeleting"
              class="block w-full rounded-lg border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-[#121214] p-3 text-sm font-mono text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 dark:placeholder-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-900 dark:focus:ring-zinc-100 focus:border-zinc-900 dark:focus:border-zinc-100 transition-colors disabled:opacity-50"
              placeholder="example1.com&#10;example2.com&#10;.example3.org"></textarea>

    <div x-show="servers.length > 0" x-cloak class="mt-4">
        <div class="flex items-center gap-2 mb-2">
            <span class="text-xs font-semibold text-zinc-900 dark:text-zinc-100 uppercase tracking-wider">Servers to Search</span>
            <span class="text-[11px] text-zinc-500 dark:text-zinc-400">
                (<span x-text="selectedServerIds.length"></span> of <span x-text="servers.length"></span> selected, fewer servers = faster search)
            </span>
        </div>
        <div class="flex flex-wrap gap-2">
            <template x-for="server in servers" :key="server.server_id">
                <button type="button"
                        @click="toggleServer(server.server_id)"
                        :disabled="isSearching || isDeleting"
                        :aria-pressed="isServerSelected(server.server_id)"
                        :class="isServerSelected(server.server_id)
                            ? 'border-zinc-900 dark:border-zinc-100 bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900'
                            : 'border-zinc-200 dark:border-zinc-800 bg-white dark:bg-[#121214] text-zinc-500 dark:text-zinc-400 line-through'"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium border rounded-md transition-colors cursor-pointer disabled:opacity-50">
                    <x-lucide-server class="size-3.5" />
                    <span x-text="server.name"></span>
                </button>
            </template>
        </div>
    </div>

    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-end gap-3 mt-3.5">
        <button type="button"
                @click="searchDomains()"
                :disabled="isSearching || isDeleting || parsedInputDomains.length === 0"
                class="inline-flex items-center justify-center gap-2 px-4 py-2 border border-transparent dark:border-zinc-700/80 text-sm font-medium rounded-lg text-white bg-zinc-900 hover:bg-zinc-800 disabled:opacity-50 focus:outline-none transition-colors shadow-sm cursor-pointer shrink-0">
            <span x-show="isSearching" class="animate-spin inline-block size-3.5 border-2 border-white border-t-transparent rounded-full mr-1"></span>
            <x-lucide-search x-show="!isSearching" class="size-4" />
            <span x-text="isSearching ? `Searching... ${searchProgress.checked}/${searchProgress.total}` : 'Search Domains'"></span>
        </button>
    </div>
</div>
