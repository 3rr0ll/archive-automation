<div class="bg-white border border-zinc-200 rounded-lg">
    <ul role="list">
        <!-- Dynamic list rendering from Alpine items state -->
        <template x-for="(item, index) in filteredItems" :key="item.id">
            <li class="bg-white hover:bg-zinc-50/50 transition-colors relative"
                x-data="{ open: false }"
                :class="[
                    index === 0 ? 'rounded-t-lg' : '',
                    index === filteredItems.length - 1 ? 'rounded-b-lg' : 'border-b border-zinc-200',
                    open ? 'z-30' : 'z-10'
                ]">
                <!-- Row Header (Always visible) -->
                <div class="px-4 py-4 sm:px-6 flex items-center justify-between"
                     :class="[
                         index === 0 ? 'rounded-t-lg' : '',
                         index === filteredItems.length - 1 && editingId !== item.id ? 'rounded-b-lg' : '',
                         editingId === item.id ? 'border-b border-zinc-100 bg-zinc-50/40' : ''
                     ]">
                    <div class="flex items-center gap-4 flex-1 min-w-0 pr-4">
                        <div class="flex-shrink-0 size-9 rounded-lg bg-zinc-100 border border-zinc-200/80 flex items-center justify-center text-zinc-600">
                            <x-lucide-server class="h-4.5 w-4.5 text-zinc-700" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2.5">
                                <span class="text-sm font-semibold text-zinc-900 truncate" x-text="item.name"></span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium"
                                      :class="item.is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200/60' : 'bg-zinc-100 text-zinc-600 border border-zinc-200'"
                                      x-text="item.is_active ? 'Active' : 'Inactive'">
                                </span>
                            </div>
                            <div class="flex items-center gap-3 mt-1 text-xs text-zinc-500 font-mono">
                                <span class="flex items-center gap-1">
                                    <span class="text-zinc-400 font-sans font-medium text-[11px]">ID:</span>
                                    <span class="text-zinc-700" x-text="item.server_id"></span>
                                </span>
                                <span class="text-zinc-300">•</span>
                                <span class="flex items-center gap-1">
                                    <span class="text-zinc-400 font-sans font-medium text-[11px]">IP:</span>
                                    <span class="text-zinc-700" x-text="item.ip_address"></span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-6 flex-shrink-0">
                        <div class="hidden sm:flex items-center gap-4 text-xs text-zinc-500 whitespace-nowrap">
                            <span x-text="`Added ${formatDate(item.created_at)}`"></span>
                        </div>

                        <!-- Dropdown Menu -->
                        <div class="relative">
                            <button type="button" 
                                    @click="open = !open" 
                                    @click.away="open = false" 
                                    class="text-zinc-400 hover:text-zinc-600 focus:outline-none rounded p-1.5 hover:bg-zinc-100 transition-colors cursor-pointer">
                                <x-lucide-ellipsis-vertical class="h-4 w-4" />
                            </button>
                            <div x-show="open" 
                                 x-transition.opacity 
                                 style="display: none;" 
                                 class="absolute right-0 z-50 w-44 rounded-md bg-white py-1 border border-zinc-200 focus:outline-none shadow-lg text-sm"
                                 :class="filteredItems.length > 1 && index === filteredItems.length - 1 ? 'bottom-full mb-2 origin-bottom-right' : 'top-full mt-2 origin-top-right'" 
                                 role="menu">
                                <button type="button" 
                                        @click="copyText(item.ip_address, 'IP Address'); open = false;" 
                                        class="block w-full text-left px-4 py-2 text-zinc-700 hover:bg-zinc-100 cursor-pointer" 
                                        role="menuitem">
                                    Copy Server IP
                                </button>
                                <button type="button" 
                                        @click="copyText(String(item.server_id), 'Server ID'); open = false;" 
                                        class="block w-full text-left px-4 py-2 text-zinc-700 hover:bg-zinc-100 cursor-pointer" 
                                        role="menuitem">
                                    Copy Server ID
                                </button>
                                <button type="button" 
                                        @click="copyText(item.name, 'Server Name'); open = false;" 
                                        class="block w-full text-left px-4 py-2 text-zinc-700 hover:bg-zinc-100 cursor-pointer" 
                                        role="menuitem">
                                    Copy Name
                                </button>
                                <div class="border-t border-zinc-100 my-1"></div>
                                <button type="button" 
                                        @click="toggleActive(item); open = false;" 
                                        class="block w-full text-left px-4 py-2 text-zinc-700 hover:bg-zinc-100 cursor-pointer" 
                                        role="menuitem">
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
            <li class="px-6 py-12 text-center text-zinc-500 text-sm">
                <div class="flex flex-col items-center justify-center">
                    <div class="size-10 rounded-full bg-zinc-100 flex items-center justify-center mb-3">
                        <x-lucide-server class="size-5 text-zinc-400" />
                    </div>
                    <span class="font-medium text-zinc-700 mb-1">No servers found</span>
                    <span class="text-xs text-zinc-400" x-text="searchQuery ? 'Try adjusting your search or filters.' : 'Sync servers from RunCloud using the button above.'"></span>
                </div>
            </li>
        </template>
    </ul>
</div>
