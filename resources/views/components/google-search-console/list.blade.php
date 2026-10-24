<div class="bg-white dark:bg-[#18181b] border border-zinc-200 dark:border-zinc-800 rounded-lg transition-colors">
    <!-- Empty State (No accounts in database) -->
    <template x-if="accounts.length === 0">
        <div class="p-12 text-center">
            <div class="size-12 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center mx-auto mb-3 text-zinc-400 dark:text-zinc-500">
                <x-lucide-globe class="size-6" />
            </div>
            <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">No Google account connected</h3>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1 max-w-sm mx-auto">
                Connect your Google account with OAuth to automatically manage and verify domains in Search Console.
            </p>
            <a href="{{ route('google.redirect') }}"
               class="mt-4 inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md text-white bg-zinc-900 hover:bg-zinc-800 border border-transparent dark:border-zinc-700/80 transition cursor-pointer shadow-sm">
                <x-lucide-log-in class="size-3.5" />
                <span>Connect with Google (OAuth)</span>
            </a>
        </div>
    </template>

    <!-- Filtered Empty State (No match for search) -->
    <template x-if="accounts.length > 0 && filteredAccounts.length === 0">
        <div class="p-12 text-center">
            <div class="size-10 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center mx-auto mb-3 text-zinc-400 dark:text-zinc-500">
                <x-lucide-search class="size-5" />
            </div>
            <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">No matching accounts found</h3>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">Try adjusting your search terms or status filters.</p>
        </div>
    </template>

    <!-- Table Rows -->
    <template x-if="filteredAccounts.length > 0">
        <ul role="list">
            <template x-for="(account, index) in filteredAccounts" :key="account.id">
                <li class="bg-white dark:bg-[#18181b] hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40 transition-colors relative"
                    x-data="{ open: false }"
                    :class="[
                        index === 0 ? 'rounded-t-lg' : '',
                        index === filteredAccounts.length - 1 ? 'rounded-b-lg' : 'border-b border-zinc-200 dark:border-zinc-800',
                        open ? 'z-30' : 'z-10'
                    ]">
                    <div class="px-4 py-4 sm:px-6 flex items-center justify-between"
                         :class="[
                             index === 0 ? 'rounded-t-lg' : '',
                             index === filteredAccounts.length - 1 ? 'rounded-b-lg' : ''
                         ]">
                        <div class="flex items-center gap-3.5 flex-1 min-w-0 pr-4">
                            <div class="flex-shrink-0 text-zinc-400 dark:text-zinc-500">
                                <x-lucide-globe class="h-4 w-4" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2.5">
                                    <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 truncate" x-text="account.name"></span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium"
                                          :class="account.is_active 
                                              ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/60' 
                                              : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-700'"
                                          x-text="account.is_active ? 'Active' : 'Inactive'">
                                    </span>
                                </div>
                                <div class="flex flex-wrap items-center gap-3 mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                    <span class="font-mono text-zinc-600 dark:text-zinc-400 truncate max-w-sm" x-text="account.client_email"></span>
                                    <template x-if="account.owner_email">
                                        <span class="flex items-center gap-1.5">
                                            <span class="text-zinc-300 dark:text-zinc-700">•</span>
                                            <span>Owner: <strong class="font-medium text-zinc-700 dark:text-zinc-300" x-text="account.owner_email"></strong></span>
                                        </span>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <!-- Right Actions -->
                        <div class="flex items-center justify-between gap-6 flex-shrink-0">
                            <div class="hidden sm:flex items-center gap-4 text-xs text-zinc-500 dark:text-zinc-400 whitespace-nowrap">
                                <span x-text="`Added ${formatDate(account.created_at)}`"></span>
                            </div>

                            <!-- Dropdown Menu -->
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
                                     :class="filteredAccounts.length > 1 && index === filteredAccounts.length - 1 ? 'bottom-full mb-2 origin-bottom-right' : 'top-full mt-2 origin-top-right'"
                                     role="menu">
                                    
                                    <!-- Test Connection -->
                                    <button type="button" 
                                            @click="testAccount(account); open = false;" 
                                            :disabled="testingId === account.id"
                                            class="flex items-center gap-2 w-full text-left px-4 py-2 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 cursor-pointer disabled:opacity-50" 
                                            role="menuitem">
                                        <x-lucide-activity class="size-4 text-zinc-400 dark:text-zinc-500" />
                                        <span x-text="testingId === account.id ? 'Testing...' : 'Test Connection'"></span>
                                    </button>

                                    <!-- Activate Account -->
                                    <template x-if="!account.is_active">
                                        <button type="button" 
                                                @click="activateAccount(account); open = false;" 
                                                :disabled="activatingId === account.id"
                                                class="flex items-center gap-2 w-full text-left px-4 py-2 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/30 cursor-pointer disabled:opacity-50" 
                                                role="menuitem">
                                            <x-lucide-check class="size-4" />
                                            <span x-text="activatingId === account.id ? 'Activating...' : 'Set Active'"></span>
                                        </button>
                                    </template>

                                    <!-- Delete Account -->
                                    <button type="button" 
                                            @click="deleteAccount(account); open = false;" 
                                            class="flex items-center gap-2 w-full text-left px-4 py-2 text-red-600 dark:text-rose-300/90 hover:bg-red-50 dark:hover:bg-rose-950/30 cursor-pointer" 
                                            role="menuitem">
                                        <x-lucide-trash-2 class="size-4 text-red-500 dark:text-rose-400" />
                                        <span>Delete</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </li>
            </template>
        </ul>
    </template>
</div>
