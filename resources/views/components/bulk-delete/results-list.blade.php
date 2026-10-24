<div class="bg-white dark:bg-[#18181b] border border-zinc-200 dark:border-zinc-800 rounded-lg transition-colors">
    <!-- Selection & Bulk Action Top Bar -->
    <div x-show="foundDeployments.length > 0" class="px-4 py-3 bg-zinc-50/50 dark:bg-zinc-900/40 border-b border-zinc-200 dark:border-zinc-800 flex items-center justify-between">
        <label class="inline-flex items-center gap-2.5 text-xs font-medium text-zinc-700 dark:text-zinc-300 cursor-pointer select-none">
            <input type="checkbox"
                   :checked="allFoundSelected"
                   @change="toggleSelectAll()"
                   class="rounded border-zinc-300 dark:border-zinc-700 text-zinc-900 dark:text-zinc-100 focus:ring-zinc-500 size-4">
            <span>Select All (<span x-text="selectedIds.length"></span> of <span x-text="filteredFound.length"></span> selected)</span>
        </label>

        <div class="flex items-center gap-2">
            <span class="text-xs text-zinc-500 dark:text-zinc-400" x-text="`${foundDeployments.length} found`"></span>
        </div>
    </div>

    <!-- Empty State (No deployments matching search) -->
    <template x-if="filteredFound.length === 0">
        <div class="px-6 py-12 text-center text-zinc-500 dark:text-zinc-400 text-sm">
            <div class="flex flex-col items-center justify-center">
                <div class="size-10 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center mb-3 text-zinc-400 dark:text-zinc-500">
                    <x-lucide-globe class="size-5" />
                </div>
                <span class="font-medium text-zinc-900 dark:text-zinc-100">No matching deployments found</span>
                <span class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">None of the searched domains match existing deployments.</span>
            </div>
        </div>
    </template>

    <!-- List Items (Row style matching users/servers/dashboard) -->
    <ul role="list" x-show="filteredFound.length > 0">
        <template x-for="(item, index) in paginatedFound" :key="item.id">
            <li class="bg-white dark:bg-[#18181b] hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40 transition-colors relative"
                x-data="{ open: false }"
                :class="[
                    index === 0 && foundDeployments.length === 0 ? 'rounded-t-lg' : '',
                    index === paginatedFound.length - 1 ? 'rounded-b-lg' : 'border-b border-zinc-200 dark:border-zinc-800',
                    open ? 'z-30' : 'z-10'
                ]">
                <div class="px-4 py-4 sm:px-6 flex items-center justify-between">
                    <!-- Checkbox & Domain Info -->
                    <div class="flex items-center gap-3.5 flex-1 min-w-0 pr-4">
                        <input type="checkbox"
                               :disabled="item.status === 'processing'"
                               :checked="selectedIds.includes(item.id)"
                               @change="toggleSelection(item.id)"
                               class="rounded border-zinc-300 dark:border-zinc-700 text-zinc-900 dark:text-zinc-100 focus:ring-zinc-500 size-4 disabled:opacity-40 shrink-0">

                        <!-- Initial Avatar -->
                        <div class="size-9 rounded-full bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900 flex items-center justify-center font-bold text-xs shrink-0"
                             x-text="getInitial(item.domain)">
                        </div>

                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2.5 flex-wrap">
                                <template x-if="item.source === 'runcloud'">
                                    <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 truncate"
                                          x-text="item.domain"></span>
                                </template>
                                <template x-if="item.source !== 'runcloud'">
                                    <a :href="`/deployments/${item.hash || item.id}/logs`"
                                       class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 hover:underline truncate"
                                       x-text="item.domain"></a>
                                </template>

                                <!-- Status Badge -->
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium capitalize"
                                      :class="{
                                          'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/60': item.status === 'completed',
                                          'bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700': item.status === 'pending' || item.status === 'runcloud',
                                          'bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-400 border border-blue-200/60 dark:border-blue-800/60': item.status === 'processing',
                                          'bg-red-50 dark:bg-rose-950/40 text-red-700 dark:text-rose-300/90 border border-red-200/60 dark:border-rose-900/40': item.status === 'failed'
                                      }"
                                      x-text="item.status === 'runcloud' ? 'RunCloud App' : item.status">
                                </span>
                            </div>

                            <!-- Sub details -->
                            <div class="flex items-center gap-3 mt-1 text-xs text-zinc-500 dark:text-zinc-400 flex-wrap">
                                <template x-if="item.server">
                                    <span class="flex items-center gap-1 font-mono">
                                        <x-lucide-server class="size-3.5 text-zinc-400 dark:text-zinc-500" />
                                        <span x-text="item.server.name"></span>
                                    </span>
                                </template>
                                <template x-if="item.server">
                                    <span class="text-zinc-300 dark:text-zinc-700">•</span>
                                </template>
                                <template x-if="item.user">
                                    <span class="flex items-center gap-1">
                                        <x-lucide-user class="size-3.5 text-zinc-400 dark:text-zinc-500" />
                                        <span x-text="item.user.name || item.user.email"></span>
                                    </span>
                                </template>
                                <template x-if="item.user">
                                    <span class="text-zinc-300 dark:text-zinc-700">•</span>
                                </template>
                                <template x-if="item.source === 'runcloud'">
                                    <span class="text-zinc-600 dark:text-zinc-400 font-medium">RunCloud Direct</span>
                                </template>
                                <template x-if="item.source !== 'runcloud'">
                                    <span>Deployed <span x-text="formatDate(item.created_at)"></span></span>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- Right Actions -->
                    <div class="flex items-center justify-between gap-4 flex-shrink-0">
                        <div class="relative">
                            <button type="button"
                                    @click="open = !open"
                                    @click.away="open = false"
                                    class="text-zinc-400 hover:text-zinc-600 dark:text-zinc-500 dark:hover:text-zinc-300 focus:outline-none rounded p-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors cursor-pointer"
                                    title="Actions">
                                <x-lucide-ellipsis-vertical class="h-4 w-4" />
                            </button>

                            <div x-show="open"
                                 x-transition.opacity
                                 style="display: none;"
                                 class="absolute right-0 z-50 w-44 rounded-md bg-white dark:bg-[#18181b] py-1 border border-zinc-200 dark:border-zinc-800 shadow-lg text-sm focus:outline-none"
                                 :class="paginatedFound.length > 1 && index === paginatedFound.length - 1 ? 'bottom-full mb-2 origin-bottom-right' : 'top-full mt-2 origin-top-right'"
                                 role="menu">
                                <template x-if="item.source !== 'runcloud'">
                                    <a :href="`/deployments/${item.hash || item.id}/logs`"
                                       class="flex items-center gap-2 w-full text-left px-4 py-2 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800/80 cursor-pointer"
                                       role="menuitem">
                                        <x-lucide-file-text class="size-4 text-zinc-400 dark:text-zinc-500" />
                                        <span>View Logs</span>
                                    </a>
                                </template>
                                <a :href="`https://${item.domain}`"
                                   target="_blank"
                                   rel="noopener"
                                   class="flex items-center gap-2 w-full text-left px-4 py-2 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800/80 cursor-pointer"
                                   role="menuitem">
                                    <x-lucide-external-link class="size-4 text-zinc-400 dark:text-zinc-500" />
                                    <span>Visit Site</span>
                                </a>
                                <template x-if="item.status !== 'processing'">
                                    <div>
                                        <div class="border-t border-zinc-100 dark:border-zinc-800 my-1"></div>
                                        <button type="button"
                                                @click="confirmDeleteSingle(item); open = false;"
                                                class="flex items-center gap-2 w-full text-left px-4 py-2 text-red-600 dark:text-rose-400 hover:bg-red-50 dark:hover:bg-rose-950/30 cursor-pointer"
                                                role="menuitem">
                                            <x-lucide-trash-2 class="size-4 text-red-500 dark:text-rose-400" />
                                            <span>Delete</span>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </li>
        </template>
    </ul>

    <!-- Pagination Footer (Matches users.list / servers.list / domain-list) -->
    <div x-show="filteredFound.length > 0" class="px-4 py-3 sm:px-6 border-t border-zinc-200 dark:border-zinc-800 flex flex-col sm:flex-row items-center justify-between gap-4 bg-zinc-50/30 dark:bg-zinc-900/30">
        <div class="text-xs text-zinc-500 dark:text-zinc-400">
            Showing <span class="font-medium text-zinc-900 dark:text-zinc-100" x-text="((currentPage - 1) * perPage) + 1"></span>
            to <span class="font-medium text-zinc-900 dark:text-zinc-100" x-text="Math.min(currentPage * perPage, filteredFound.length)"></span>
            of <span class="font-medium text-zinc-900 dark:text-zinc-100" x-text="filteredFound.length"></span> deployments
        </div>

        <div class="flex items-center gap-2">
            <button type="button"
                    @click="prevPage()"
                    :disabled="currentPage === 1"
                    class="inline-flex items-center px-3 py-1.5 border border-zinc-200 dark:border-zinc-800 text-xs font-medium rounded-lg text-zinc-700 dark:text-zinc-300 bg-white dark:bg-[#18181b] hover:bg-zinc-50 dark:hover:bg-zinc-800 disabled:opacity-40 transition">
                <x-lucide-chevron-left class="size-3.5 mr-1" />
                <span>Previous</span>
            </button>
            <span class="text-xs text-zinc-500 dark:text-zinc-400 px-1">
                Page <span x-text="currentPage"></span> of <span x-text="totalPages"></span>
            </span>
            <button type="button"
                    @click="nextPage()"
                    :disabled="currentPage === totalPages"
                    class="inline-flex items-center px-3 py-1.5 border border-zinc-200 dark:border-zinc-800 text-xs font-medium rounded-lg text-zinc-700 dark:text-zinc-300 bg-white dark:bg-[#18181b] hover:bg-zinc-50 dark:hover:bg-zinc-800 disabled:opacity-40 transition">
                <span>Next</span>
                <x-lucide-chevron-right class="size-3.5 ml-1" />
            </button>
        </div>
    </div>
</div>
