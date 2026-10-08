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
                            <svg class="size-4 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 9l4-4 4 4m0 6l-4 4-4-4" /></svg>
                        </div>
                    </div>
                    
                    <div class="flex flex-col gap-1.5 mt-4">
                        <div class="flex items-center gap-1.5 text-[13px] text-zinc-800 font-medium">
                            <svg class="size-3.5 shrink-0 text-zinc-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9a3 3 0 100-6 3 3 0 000 6zM8 15a3 3 0 100-6 3 3 0 000 6zM16 15a3 3 0 100-6 3 3 0 000 6zM8 9h8" /></svg>
                            <span class="truncate" x-text="domain.commit_msg"></span>
                        </div>
                        <div class="flex items-center gap-1.5 text-[13px] text-zinc-500">
                            <svg class="size-3.5 shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.477 2 2 6.477 2 12c0 4.42 2.865 8.166 6.839 9.489.5.092.682-.217.682-.482 0-.237-.008-.866-.013-1.7-2.782.604-3.369-1.34-3.369-1.34-.454-1.156-1.11-1.464-1.11-1.464-.908-.62.069-.608.069-.608 1.003.07 1.531 1.03 1.531 1.03.892 1.529 2.341 1.087 2.91.831.092-.646.35-1.086.636-1.336-2.22-.253-4.555-1.11-4.555-4.943 0-1.091.39-1.984 1.029-2.683-.103-.253-.446-1.27.098-2.647 0 0 .84-.269 2.75 1.025A9.578 9.578 0 0112 6.836c.85.004 1.705.114 2.504.336 1.909-1.294 2.747-1.025 2.747-1.025.546 1.379.203 2.394.1 2.647.64.699 1.028 1.592 1.028 2.683 0 3.842-2.339 4.687-4.566 4.935.359.309.678.919.678 1.852 0 1.336-.012 2.415-.012 2.743 0 .267.18.578.688.48C19.138 20.161 22 16.416 22 12c0-5.523-4.477-10-10-10z"/></svg>
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
                                <svg class="size-3.5 shrink-0 text-zinc-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9a3 3 0 100-6 3 3 0 000 6zM8 15a3 3 0 100-6 3 3 0 000 6zM16 15a3 3 0 100-6 3 3 0 000 6zM8 9h8" /></svg>
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
                            <button class="p-1.5 text-zinc-400 hover:text-zinc-700 transition rounded-md border border-zinc-200 hover:bg-zinc-50"><svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg></button>
                            <button class="p-1.5 text-zinc-400 hover:text-zinc-700 transition rounded-md border border-zinc-200 hover:bg-zinc-50"><svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4" /></svg></button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </template>
</div>

