<div class="lg:col-span-8 xl:col-span-9">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-sm font-semibold text-zinc-900">
            Deployed Domains
            <span class="text-xs font-normal text-zinc-500 ml-1.5" x-text="`(${displayedDeployments.length})`"></span>
        </h2>
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
            <template x-for="item in displayedDeployments" :key="'grid-'+item.id">
                <a :href="'/deployments/' + (item.hash || item.id) + '/logs'" 
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
                    </div>
                </a>
            </template>
        </div>
    </template>
    
    <!-- List View -->
    <template x-if="displayedDeployments.length > 0 && $store.domainView.mode === 'list'">
        <div class="bg-white border border-zinc-200 rounded-lg overflow-hidden divide-y divide-zinc-200">
            <template x-for="item in displayedDeployments" :key="'list-'+item.id">
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
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </template>
</div>


