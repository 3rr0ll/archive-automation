<div class="lg:col-span-8 xl:col-span-9" x-data="domainListData()">
    <div class="mb-4">
        <h2 class="text-sm font-semibold text-zinc-900">Domains</h2>
    </div>
    
    <!-- Grid View -->
    <template x-if="$store.domainView.mode === 'grid'">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <template x-for="domain in domains" :key="'grid-'+domain.name">
                <div class="bg-white border border-zinc-200 rounded-lg transition group cursor-pointer p-4 flex flex-col justify-between min-h-[140px]">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-3">
                            <div class="size-8 rounded-full flex items-center justify-center shrink-0 border border-zinc-200/60" :class="domain.avatar_bg" x-html="domain.avatar_content">
                            </div>
                            <div class="flex flex-col">
                                <h3 class="text-[13px] font-semibold text-zinc-900 group-hover:text-black" x-text="domain.name"></h3>
                                <a href="#" class="text-[13px] text-zinc-500 hover:text-zinc-800 transition" x-text="domain.url"></a>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="size-6 rounded-full border border-zinc-200 flex items-center justify-center relative bg-zinc-50">
                                <span class="absolute -right-0.5 -top-0.5 size-[7px] bg-[#0070F3] rounded-full ring-2 ring-white"></span>
                                <span x-html="domain.status_icon"></span>
                            </div>
                            <x-lucide-chevrons-up-down class="size-4 text-zinc-400" />
                        </div>
                    </div>
                    
                    <div class="flex flex-col gap-1.5 mt-4">
                        <div class="flex items-center gap-1.5 text-[13px] text-zinc-800 font-medium">
                            <x-lucide-git-branch class="size-3.5 shrink-0 text-zinc-600" />
                            <span class="truncate" x-text="domain.commit_msg"></span>
                        </div>
                        <div class="flex items-center gap-1.5 text-[13px] text-zinc-500">
                            <x-lucide-github class="size-3.5 shrink-0" />
                            <span x-text="`${domain.repo} &middot; ${domain.date}`"></span>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </template>
    
    <!-- List View -->
    <template x-if="$store.domainView.mode === 'list'">
        <div class="bg-white border border-zinc-200 rounded-lg overflow-hidden divide-y divide-zinc-200">
            <template x-for="domain in domains" :key="'list-'+domain.name">
                <div class="flex items-center justify-between p-4 hover:bg-zinc-50 transition group cursor-pointer">
                    <!-- Left Section -->
                    <div class="flex items-center gap-4">
                        <div class="size-8 rounded-full flex items-center justify-center shrink-0 border border-zinc-200/60" :class="domain.avatar_bg" x-html="domain.avatar_content"></div>
                        <div>
                            <h3 class="text-sm font-semibold text-zinc-900 group-hover:text-black" x-text="domain.name"></h3>
                            <a href="#" class="text-xs text-zinc-500 hover:underline" x-text="domain.url"></a>
                        </div>
                    </div>
                    <!-- Right Section -->
                    <div class="flex items-center gap-6">
                        <div class="hidden sm:block text-right">
                            <div class="flex items-center justify-end gap-1.5 text-xs text-zinc-800 font-medium">
                                <span class="truncate max-w-[200px]" x-text="domain.commit_msg"></span>
                                <x-lucide-git-branch class="size-3.5 shrink-0 text-zinc-600" />
                            </div>
                            <div class="flex items-center justify-end gap-1.5 text-xs text-zinc-500 mt-1">
                                <span x-text="`${domain.repo} &middot; ${domain.date}`"></span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="size-6 rounded-full border border-zinc-200 flex items-center justify-center relative bg-zinc-50 mr-2">
                                <span class="absolute -right-0.5 -top-0.5 size-[7px] bg-[#0070F3] rounded-full ring-2 ring-white"></span>
                                <span x-html="domain.status_icon"></span>
                            </div>
                            <button class="p-1.5 text-zinc-400 hover:text-zinc-700 transition rounded-md border border-zinc-200 hover:bg-zinc-50"><x-lucide-bar-chart-2 class="size-4" /></button>
                            <button class="p-1.5 text-zinc-400 hover:text-zinc-700 transition rounded-md border border-zinc-200 hover:bg-zinc-50"><x-lucide-chevrons-up-down class="size-4" /></button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </template>
</div>

