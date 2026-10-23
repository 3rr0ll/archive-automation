<div class="bg-white dark:bg-[#18181b] border border-zinc-200 dark:border-zinc-800 rounded-lg transition-colors">
    <ul role="list">
        <!-- Dynamic list rendering from Alpine items state -->
        <template x-for="(user, index) in paginatedItems" :key="user.id">
            <li class="bg-white dark:bg-[#18181b] hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40 transition-colors relative"
                x-data="{ open: false }"
                :class="[
                    index === 0 ? 'rounded-t-lg' : '',
                    index === paginatedItems.length - 1 ? 'rounded-b-lg' : 'border-b border-zinc-200 dark:border-zinc-800',
                    open ? 'z-30' : 'z-10'
                ]">
                <div class="px-4 py-4 sm:px-6 flex items-center justify-between"
                     :class="[
                         index === 0 ? 'rounded-t-lg' : '',
                         index === paginatedItems.length - 1 ? 'rounded-b-lg' : ''
                     ]">
                    <!-- User info (Avatar, Name, Email) -->
                    <div class="flex items-center gap-3.5 flex-1 min-w-0 pr-4">
                        <div class="size-9 rounded-full bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900 flex items-center justify-center font-bold text-xs shrink-0"
                             x-text="getInitial(user.name)">
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 truncate" x-text="user.name"></span>
                                <template x-if="user.id === currentUserId">
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-700">You</span>
                                </template>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium capitalize ml-1"
                                      :class="user.role === 'admin' 
                                          ? 'bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900' 
                                          : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700'"
                                      x-text="user.role || 'user'">
                                </span>
                            </div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400 truncate mt-0.5" x-text="user.email"></div>
                        </div>
                    </div>

                    <!-- Right Section: Date & Actions Dropdown -->
                    <div class="flex items-center justify-between gap-6 flex-shrink-0">
                        <div class="hidden sm:flex items-center gap-4 text-xs text-zinc-500 dark:text-zinc-400 whitespace-nowrap">
                            <span x-text="`Added ${formatDate(user.created_at)}`"></span>
                        </div>

                        <!-- Dropdown Menu (Floating with smart positioning) -->
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
                                 :class="paginatedItems.length > 1 && index === paginatedItems.length - 1 ? 'bottom-full mb-2 origin-bottom-right' : 'top-full mt-2 origin-top-right'"
                                 role="menu">
                                <button type="button" 
                                        @click="openEditModal(user); open = false;" 
                                        class="flex items-center gap-2 w-full text-left px-4 py-2 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 cursor-pointer" 
                                        role="menuitem">
                                    <x-lucide-pencil class="size-4 text-zinc-400 dark:text-zinc-500" />
                                    <span>Edit Profile</span>
                                </button>
                                <button type="button" 
                                        @click="openPasswordModal(user); open = false;" 
                                        class="flex items-center gap-2 w-full text-left px-4 py-2 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 cursor-pointer" 
                                        role="menuitem">
                                    <x-lucide-key-round class="size-4 text-zinc-400 dark:text-zinc-500" />
                                    <span>Reset Password</span>
                                </button>
                                <template x-if="user.id !== currentUserId && user.role !== 'admin'">
                                    <div>
                                        <div class="border-t border-zinc-100 dark:border-zinc-800 my-1"></div>
                                        <button type="button" 
                                                @click="deleteUserPrompt(user); open = false;" 
                                                class="flex items-center gap-2 w-full text-left px-4 py-2 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/30 cursor-pointer" 
                                                role="menuitem">
                                            <x-lucide-trash-2 class="size-4 text-red-500" />
                                            <span>Delete User</span>
                                        </button>
                                    </div>
                                </template>
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
                        <x-lucide-users class="size-5 text-zinc-400 dark:text-zinc-500" />
                    </div>
                    <span class="font-medium text-zinc-700 dark:text-zinc-200 mb-1">No users found</span>
                    <span class="text-xs text-zinc-400 dark:text-zinc-500" x-text="searchQuery ? 'Try adjusting your search or filters.' : 'Add a new user using the button above.'"></span>
                </div>
            </li>
        </template>
    </ul>

    <!-- Pagination Controls -->
    <template x-if="filteredItems.length > 0">
        <div class="px-4 py-3 sm:px-6 flex flex-col sm:flex-row items-center justify-between gap-4 border-t border-zinc-200 dark:border-zinc-800 text-xs text-zinc-600 dark:text-zinc-400 bg-zinc-50/50 dark:bg-zinc-900/50 rounded-b-lg">
            <!-- Count and Per Page selector -->
            <div class="flex items-center gap-3 w-full sm:w-auto justify-between sm:justify-start">
                <span>
                    Showing <span class="font-medium text-zinc-900 dark:text-zinc-100" x-text="paginationFrom"></span> to <span class="font-medium text-zinc-900 dark:text-zinc-100" x-text="paginationTo"></span> of <span class="font-medium text-zinc-900 dark:text-zinc-100" x-text="filteredItems.length"></span> users
                </span>
                <div class="flex items-center gap-1.5">
                    <span class="text-zinc-400 dark:text-zinc-500">Per page:</span>
                    <div class="relative" x-data="{ open: false }">
                        <button type="button" 
                                @click="open = !open" 
                                @click.away="open = false" 
                                class="inline-flex items-center justify-between gap-1.5 h-7 px-2.5 text-xs font-medium border border-zinc-200 dark:border-zinc-800 rounded bg-white dark:bg-[#18181b] text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-800 outline-none focus:outline-none transition cursor-pointer">
                            <span class="font-medium text-zinc-900 dark:text-zinc-100" x-text="perPage"></span>
                            <x-lucide-chevron-down class="size-3 text-zinc-400 dark:text-zinc-500 transition-transform duration-200" ::class="open ? 'rotate-180' : ''" />
                        </button>
                        <div x-show="open" 
                             x-transition.opacity
                             style="display: none;" 
                             class="absolute left-0 bottom-full mb-1 z-50 w-20 rounded-md bg-white dark:bg-[#18181b] py-1 border border-zinc-200 dark:border-zinc-800 shadow-lg text-xs focus:outline-none"
                             role="menu">
                            <template x-for="val in [5, 10, 20, 50]" :key="'user-opt-'+val">
                                <button type="button" 
                                        @click="perPage = val; currentPage = 1; open = false;" 
                                        class="flex items-center justify-between w-full px-2.5 py-1.5 text-left text-xs text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 cursor-pointer"
                                        :class="perPage === val ? 'bg-zinc-50 dark:bg-zinc-800/60 font-semibold text-zinc-900 dark:text-zinc-100' : ''"
                                        role="menuitem">
                                    <span x-text="val"></span>
                                    <x-lucide-check x-show="perPage === val" class="size-3 text-zinc-900 dark:text-zinc-100" />
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
                        :class="currentPage === 1 ? 'opacity-40 cursor-not-allowed text-zinc-400 dark:text-zinc-600' : 'hover:bg-zinc-100 dark:hover:bg-zinc-800 text-zinc-700 dark:text-zinc-300 cursor-pointer'"
                        class="px-2.5 py-1 rounded border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-[#18181b] inline-flex items-center gap-1 transition">
                    <x-lucide-chevron-left class="size-3.5" />
                    <span>Prev</span>
                </button>

                <div class="flex items-center gap-1">
                    <template x-for="p in totalPages" :key="'p-'+p">
                        <button type="button"
                                x-show="p === 1 || p === totalPages || (p >= currentPage - 1 && p <= currentPage + 1)"
                                @click="goToPage(p)"
                                :class="p === currentPage ? 'bg-zinc-900 text-white border-zinc-900 dark:bg-zinc-800 dark:border-zinc-700/80 dark:text-zinc-100 shadow-xs' : 'bg-white dark:bg-[#18181b] text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-800 border-zinc-200 dark:border-zinc-800'"
                                class="min-w-[28px] h-7 px-2 rounded border text-xs font-medium transition cursor-pointer flex items-center justify-center"
                                x-text="p">
                        </button>
                    </template>
                </div>

                <button type="button" 
                        @click="nextPage()" 
                        :disabled="currentPage === totalPages"
                        :class="currentPage === totalPages ? 'opacity-40 cursor-not-allowed text-zinc-400 dark:text-zinc-600' : 'hover:bg-zinc-100 dark:hover:bg-zinc-800 text-zinc-700 dark:text-zinc-300 cursor-pointer'"
                        class="px-2.5 py-1 rounded border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-[#18181b] inline-flex items-center gap-1 transition">
                    <span>Next</span>
                    <x-lucide-chevron-right class="size-3.5" />
                </button>
            </div>
        </div>
    </template>
</div>
