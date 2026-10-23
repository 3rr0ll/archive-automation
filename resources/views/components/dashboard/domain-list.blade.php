<div class="lg:col-span-8 xl:col-span-9">
    <div class="flex items-center justify-between gap-3 mb-4">
        <h2 class="text-sm font-semibold text-zinc-900">
            Deployed Domains
            <span class="text-xs font-normal text-zinc-500 ml-1.5" x-text="`(${displayedDeployments.length})`"></span>
        </h2>
        <div x-show="$store.auth.isAdmin && displayedDeployments.length > 0" class="flex items-center gap-2">
            <label class="inline-flex items-center gap-1.5 text-xs text-zinc-600 cursor-pointer">
                <input type="checkbox"
                       :checked="allVisibleDeploymentsSelected"
                       @change="toggleVisibleDeploymentSelection()"
                       class="rounded border-zinc-300 text-zinc-900 focus:ring-zinc-500">
                <span>Select page</span>
            </label>
            <button type="button"
                    x-show="selectedDeploymentIds.length > 0"
                    @click="deleteSelectedDeploymentsPrompt()"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-medium rounded-md border border-red-200 text-red-700 bg-white hover:bg-red-50 transition">
                <x-lucide-trash-2 class="size-3.5" />
                <span x-text="`Delete (${selectedDeploymentIds.length})`"></span>
            </button>
        </div>
    </div>
    
    <!-- Empty State -->
    <template x-if="displayedDeployments.length === 0">
        <div class="bg-white border border-zinc-200 rounded-lg p-12 text-center">
            <div class="size-12 rounded-full bg-zinc-100 flex items-center justify-center mx-auto mb-3 text-zinc-400">
                <x-lucide-globe class="size-6" />
            </div>
            <h3 class="text-sm font-semibold text-zinc-900">No domains deployed yet</h3>
            <p class="text-xs text-zinc-500 mt-1 max-w-sm mx-auto">Get started by launching a new deployment from an archived website snapshot.</p>
            <button type="button" 
                    @click="openModal()" 
                    class="mt-4 inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md text-white bg-zinc-900 hover:bg-zinc-800 transition">
                <x-lucide-plus class="size-3.5" />
                <span>New Deployment</span>
            </button>
        </div>
    </template>

    <!-- Grid View -->
    <template x-if="displayedDeployments.length > 0 && $store.domainView.mode === 'grid'">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <template x-for="item in paginatedDeployments" :key="'grid-'+item.id">
                <div @click="window.location.href = '/deployments/' + (item.hash || item.id) + '/logs'"
                   class="bg-white border border-zinc-200 rounded-lg transition hover:border-zinc-300 group p-4 flex flex-col justify-between min-h-[140px] cursor-pointer">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="size-8 rounded-full flex items-center justify-center shrink-0 border border-zinc-200 bg-zinc-900 text-white font-bold text-xs">
                                <span x-text="(item.domain ? item.domain.charAt(0).toUpperCase() : 'D')"></span>
                            </div>
                            <div class="flex flex-col min-w-0">
                                <h3 class="text-[13px] font-semibold text-zinc-900 group-hover:text-black truncate" x-text="formatDomainName(item.domain)"></h3>
                                <span class="text-[12px] text-zinc-500 truncate" x-text="item.domain"></span>
                            </div>
                        </div>
                        <div class="flex items-center gap-1.5 shrink-0">
                            <input x-show="$store.auth.isAdmin && item.status !== 'processing'"
                                   type="checkbox"
                                   :checked="selectedDeploymentIds.includes(item.id)"
                                   @click.stop="toggleDeploymentSelection(item.id)"
                                   class="rounded border-zinc-300 text-zinc-900 focus:ring-zinc-500"
                                   title="Select deployment">
                            <!-- In Progress Badge for active first in line -->
                            <template x-if="isItemActive(item)">
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[10px] font-medium bg-zinc-100 text-zinc-800 border border-zinc-200">
                                    <span class="size-1.5 rounded-full bg-zinc-900 animate-pulse"></span>
                                    In Progress
                                </span>
                            </template>
                            <!-- Active Badge for completed deployments -->
                            <template x-if="!isItemActive(item)">
                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-medium bg-zinc-50 text-zinc-700 border border-zinc-200">
                                    <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                    Active
                                </span>
                            </template>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-t border-zinc-100 flex items-center justify-between">
                        <div class="flex items-center gap-1.5 text-xs text-zinc-800 font-medium min-w-0">
                            <x-lucide-server class="size-3.5 shrink-0 text-zinc-500" />
                            <span class="truncate" x-text="item.server ? item.server.name : 'Unknown server'"></span>
                            <span class="text-[10px] text-zinc-400 font-mono" x-show="item.version" x-text="`(${item.version})`"></span>
                        </div>
                        <span class="text-xs text-zinc-400 group-hover:text-zinc-600 transition flex items-center gap-1">
                            <span>Logs</span>
                            <x-lucide-arrow-right class="size-3" />
                        </span>
                        <button x-show="$store.auth.isAdmin && item.status !== 'processing'"
                                type="button"
                                @click.stop="deleteDeploymentPrompt(item)"
                                class="p-1 text-zinc-400 hover:text-red-600 transition rounded"
                                title="Delete deployment and RunCloud app">
                            <x-lucide-trash-2 class="size-3.5" />
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </template>
    
    <!-- List View -->
    <template x-if="displayedDeployments.length > 0 && $store.domainView.mode === 'list'">
        <div class="bg-white border border-zinc-200 rounded-lg overflow-hidden divide-y divide-zinc-200">
            <template x-for="item in paginatedDeployments" :key="'list-'+item.id">
                <div class="flex items-center justify-between p-4 hover:bg-zinc-50 transition group cursor-pointer"
                     @click="window.location.href = '/deployments/' + (item.hash || item.id) + '/logs'">
                    <!-- Left Section -->
                    <div class="flex items-center gap-4 min-w-0">
                        <div class="size-8 rounded-full flex items-center justify-center shrink-0 border border-zinc-200 bg-zinc-900 text-white font-bold text-xs">
                            <span x-text="(item.domain ? item.domain.charAt(0).toUpperCase() : 'D')"></span>
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-sm font-semibold text-zinc-900 group-hover:text-black truncate" x-text="formatDomainName(item.domain)"></h3>
                            <span class="text-xs text-zinc-500 truncate" x-text="item.domain"></span>
                        </div>
                    </div>
                    <!-- Right Section -->
                    <div class="flex items-center gap-6 shrink-0" @click.stop>
                        <div class="hidden sm:block text-right">
                            <div class="flex items-center justify-end gap-1.5 text-xs text-zinc-800 font-medium">
                                <span class="truncate max-w-[200px]" x-text="item.server ? item.server.name : 'Server'"></span>
                                <x-lucide-server class="size-3.5 shrink-0 text-zinc-500" />
                            </div>
                            <div class="flex items-center justify-end gap-1.5 text-[11px] text-zinc-400 mt-0.5">
                                <span class="font-mono text-[10px]" x-show="item.version" x-text="item.version"></span>
                                <span x-show="item.version">&middot;</span>
                                <span x-text="formatDate(item.created_at)"></span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <input x-show="$store.auth.isAdmin && item.status !== 'processing'"
                                   type="checkbox"
                                   :checked="selectedDeploymentIds.includes(item.id)"
                                   @click.stop="toggleDeploymentSelection(item.id)"
                                   class="rounded border-zinc-300 text-zinc-900 focus:ring-zinc-500"
                                   title="Select deployment">
                            <template x-if="isItemActive(item)">
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-xs font-medium bg-zinc-100 text-zinc-800 border border-zinc-200">
                                    <span class="size-1.5 rounded-full bg-zinc-900 animate-pulse"></span>
                                    In Progress
                                </span>
                            </template>
                            <template x-if="!isItemActive(item)">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-zinc-50 text-zinc-700 border border-zinc-200">
                                    <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                    Active
                                </span>
                            </template>
                            <a :href="'https://' + item.domain" target="_blank" rel="noopener" class="p-1.5 text-zinc-400 hover:text-zinc-700 transition rounded-md border border-zinc-200 hover:bg-zinc-50" title="Visit website" @click.stop>
                                <x-lucide-external-link class="size-3.5" />
                            </a>
                            <button x-show="$store.auth.isAdmin && item.status !== 'processing'"
                                    type="button"
                                    @click.stop="deleteDeploymentPrompt(item)"
                                    class="p-1.5 text-zinc-400 hover:text-red-600 transition rounded-md border border-zinc-200 hover:bg-red-50"
                                    title="Delete deployment and RunCloud app">
                                <x-lucide-trash-2 class="size-3.5" />
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </template>

    <!-- Pagination Controls -->
    <template x-if="displayedDeployments.length > 0">
        <div class="mt-5 flex flex-col sm:flex-row items-center justify-between gap-4 pt-3 border-t border-zinc-200 text-xs text-zinc-600">
            <!-- Count and Per Page selector -->
            <div class="flex items-center gap-3 w-full sm:w-auto justify-between sm:justify-start">
                <span>
                    Showing <span class="font-medium text-zinc-900" x-text="paginationFrom"></span> to <span class="font-medium text-zinc-900" x-text="paginationTo"></span> of <span class="font-medium text-zinc-900" x-text="displayedDeployments.length"></span> domains
                </span>
                <div class="flex items-center gap-1.5">
                    <span class="text-zinc-400">Per page:</span>
                    <div class="relative" x-data="{ open: false }">
                        <button type="button" 
                                @click="open = !open" 
                                @click.away="open = false" 
                                class="inline-flex items-center justify-between gap-1.5 h-7 px-2.5 text-xs font-medium border border-zinc-200 rounded bg-white text-zinc-700 hover:bg-zinc-50 outline-none focus:outline-none transition cursor-pointer">
                            <span class="font-medium text-zinc-900" x-text="perPage"></span>
                            <x-lucide-chevron-down class="size-3 text-zinc-400 transition-transform duration-200" ::class="open ? 'rotate-180' : ''" />
                        </button>
                        <div x-show="open" 
                             x-transition.opacity
                             style="display: none;" 
                             class="absolute left-0 bottom-full mb-1 z-50 w-20 rounded-md bg-white py-1 border border-zinc-200 shadow-lg text-xs focus:outline-none"
                             role="menu">
                            <template x-for="val in [6, 9, 15, 30]" :key="'domain-opt-'+val">
                                <button type="button" 
                                        @click="perPage = val; currentPage = 1; open = false;" 
                                        class="flex items-center justify-between w-full px-2.5 py-1.5 text-left text-xs text-zinc-700 hover:bg-zinc-100 cursor-pointer"
                                        :class="perPage === val ? 'bg-zinc-50 font-semibold text-zinc-900' : ''"
                                        role="menuitem">
                                    <span x-text="val"></span>
                                    <x-lucide-check x-show="perPage === val" class="size-3 text-zinc-900" />
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Page Buttons -->
            <div class="flex items-center gap-1.5" x-show="totalPages > 1">
                <button type="button" 
                        @click="prevPage()" 
                        :disabled="currentPage === 1"
                        :class="currentPage === 1 ? 'opacity-40 cursor-not-allowed text-zinc-400' : 'hover:bg-zinc-100 text-zinc-700 cursor-pointer'"
                        class="px-2.5 py-1 rounded border border-zinc-200 bg-white inline-flex items-center gap-1 transition">
                    <x-lucide-chevron-left class="size-3.5" />
                    <span>Prev</span>
                </button>

                <div class="flex items-center gap-1">
                    <template x-for="p in totalPages" :key="'p-'+p">
                        <button type="button"
                                x-show="p === 1 || p === totalPages || (p >= currentPage - 1 && p <= currentPage + 1)"
                                @click="goToPage(p)"
                                :class="p === currentPage ? 'bg-zinc-900 text-white border-zinc-900' : 'bg-white text-zinc-700 hover:bg-zinc-50 border-zinc-200'"
                                class="min-w-[28px] h-7 px-2 rounded border text-xs font-medium transition cursor-pointer flex items-center justify-center"
                                x-text="p">
                        </button>
                    </template>
                </div>

                <button type="button" 
                        @click="nextPage()" 
                        :disabled="currentPage === totalPages"
                        :class="currentPage === totalPages ? 'opacity-40 cursor-not-allowed text-zinc-400' : 'hover:bg-zinc-100 text-zinc-700 cursor-pointer'"
                        class="px-2.5 py-1 rounded border border-zinc-200 bg-white inline-flex items-center gap-1 transition">
                    <span>Next</span>
                    <x-lucide-chevron-right class="size-3.5" />
                </button>
            </div>
        </div>
    </template>
</div>

