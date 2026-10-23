<div class="bg-white dark:bg-[#18181b] border border-zinc-200 dark:border-zinc-800 rounded-lg transition-colors">
    <ul role="list">
        <!-- Dynamic list rendering from Alpine items state -->
        <template x-for="(item, index) in filteredItems" :key="item.id">
            <li class="bg-white dark:bg-[#18181b] hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40 transition-colors relative"
                x-data="{ open: false }"
                :class="[
                    index === 0 ? 'rounded-t-lg' : '',
                    index === filteredItems.length - 1 ? 'rounded-b-lg' : 'border-b border-zinc-200 dark:border-zinc-800',
                    open ? 'z-30' : 'z-10'
                ]">
                <!-- Row Header (Always visible) -->
                <div class="px-4 py-4 sm:px-6 flex items-center justify-between"
                     :class="[
                         index === 0 ? 'rounded-t-lg' : '',
                         index === filteredItems.length - 1 && editingId !== item.id ? 'rounded-b-lg' : '',
                         editingId === item.id ? 'border-b border-zinc-100 dark:border-zinc-800 bg-zinc-50/40 dark:bg-zinc-900/40' : ''
                     ]">
                    <div class="flex items-center gap-3.5 flex-1 min-w-0 pr-4">
                        <div class="flex-shrink-0 text-zinc-400 dark:text-zinc-500">
                            <x-lucide-server class="h-4 w-4" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2.5">
                                <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 truncate" x-text="item.name"></span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium"
                                      :class="item.is_active ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/60' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-700'"
                                      x-text="item.is_active ? 'Active' : 'Inactive'">
                                </span>
                            </div>
                            <div class="flex items-center gap-3 mt-1 text-xs text-zinc-500 dark:text-zinc-400 font-mono">
                                <span class="flex items-center gap-1">
                                    <span class="text-zinc-400 dark:text-zinc-500 font-sans font-medium text-[11px]">ID:</span>
                                    <span class="text-zinc-700 dark:text-zinc-300" x-text="item.server_id"></span>
                                </span>
                                <span class="text-zinc-300 dark:text-zinc-700">•</span>
                                <span class="flex items-center gap-1">
                                    <span class="text-zinc-400 dark:text-zinc-500 font-sans font-medium text-[11px]">IP:</span>
                                    <span class="text-zinc-700 dark:text-zinc-300" x-text="item.ip_address"></span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-6 flex-shrink-0">
                        <div class="hidden sm:flex items-center gap-4 text-xs text-zinc-500 dark:text-zinc-400 whitespace-nowrap">
                            <span x-text="`Added ${formatDate(item.created_at)}`"></span>
                        </div>

                        <!-- Dropdown Menu -->
                        <div class="relative">
                            <button type="button" 
                                    @click="open = !open" 
                                    @click.away="open = false" 
                                    class="text-zinc-400 hover:text-zinc-600 dark:text-zinc-500 dark:hover:text-zinc-300 focus:outline-none rounded p-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors cursor-pointer">
                                <x-lucide-ellipsis-vertical class="h-4 w-4" />
                            </button>
                            <div x-show="open" 
                                 x-transition.opacity 
                                 style="display: none;" 
                                 class="absolute right-0 z-50 w-44 rounded-md bg-white dark:bg-[#18181b] py-1 border border-zinc-200 dark:border-zinc-800 focus:outline-none shadow-lg text-sm"
                                 :class="filteredItems.length > 1 && index === filteredItems.length - 1 ? 'bottom-full mb-2 origin-bottom-right' : 'top-full mt-2 origin-top-right'" 
                                 role="menu">
                                <button type="button" 
                                        @click="copyText(item.ip_address, 'IP Address'); open = false;" 
                                        class="flex items-center gap-2 w-full text-left px-4 py-2 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 cursor-pointer" 
                                        role="menuitem">
                                    <x-lucide-copy class="size-4 text-zinc-400 dark:text-zinc-500" />
                                    <span>Copy Server IP</span>
                                </button>
                                <button type="button" 
                                        @click="copyText(String(item.server_id), 'Server ID'); open = false;" 
                                        class="flex items-center gap-2 w-full text-left px-4 py-2 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 cursor-pointer" 
                                        role="menuitem">
                                    <x-lucide-hash class="size-4 text-zinc-400 dark:text-zinc-500" />
                                    <span>Copy Server ID</span>
                                </button>
                                <button type="button" 
                                        @click="copyText(item.name, 'Server Name'); open = false;" 
                                        class="flex items-center gap-2 w-full text-left px-4 py-2 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 cursor-pointer" 
                                        role="menuitem">
                                    <x-lucide-copy class="size-4 text-zinc-400 dark:text-zinc-500" />
                                    <span>Copy Name</span>
                                </button>
                                <div class="border-t border-zinc-100 dark:border-zinc-800 my-1"></div>
                                <button type="button" 
                                        @click="toggleActive(item); open = false;" 
                                        class="flex items-center gap-2 w-full text-left px-4 py-2 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 cursor-pointer" 
                                        role="menuitem">
                                    <x-lucide-power class="size-4 text-zinc-400 dark:text-zinc-500" />
                                    <span x-text="item.is_active ? 'Set Inactive' : 'Set Active'"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </li>
        </template>

        <!-- Empty State -->
        <template x-if="filteredItems.length === 0">
            <li class="px-6 py-12 text-center text-zinc-500 dark:text-zinc-400 text-sm">
                <div class="flex flex-col items-center justify-center">
                    <div class="size-10 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center mb-3">
                        <x-lucide-server class="size-5 text-zinc-400 dark:text-zinc-500" />
                    </div>
                    <span class="font-medium text-zinc-700 dark:text-zinc-200 mb-1">No servers found</span>
                    <span class="text-xs text-zinc-400 dark:text-zinc-500" x-text="searchQuery ? 'Try adjusting your search or filters.' : 'Sync servers from RunCloud using the button above.'"></span>
                </div>
            </li>
        </template>
    </ul>
</div>
