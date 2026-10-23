@extends('layouts.app')

@section('content')
<div class="mx-auto py-6 space-y-6" x-data="googleSearchConsolePage(@js($accounts ?? []), @js($legacyFallback ?? false))" x-init="init()">
    <!-- Skeleton Loading State -->
    <div x-show="isLoading">
        <x-skeleton.google-search-console-skeleton />
    </div>

    <!-- Main Content -->
    <div x-show="!isLoading" x-cloak class="space-y-6">
        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 tracking-tight">Google Search Console</h1>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                    Manage service accounts for automatic domain verification, sitemap submission, and indexing.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('google.redirect') }}"
                   class="inline-flex items-center justify-center gap-2 px-4 py-2 border border-transparent dark:border-zinc-700/80 text-sm font-medium rounded-lg text-white bg-zinc-900 hover:bg-zinc-800 focus:outline-none transition-colors shadow-sm cursor-pointer">
                    <x-lucide-log-in class="w-4 h-4" />
                    <span>Connect with Google (OAuth)</span>
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-lg border border-emerald-200 dark:border-emerald-900/50 bg-emerald-50 dark:bg-emerald-950/40 px-4 py-3 text-sm text-emerald-900 dark:text-emerald-300">
                {{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="rounded-lg border border-red-200 dark:border-rose-900/50 bg-red-50 dark:bg-rose-950/40 px-4 py-3 text-sm text-red-900 dark:text-rose-300">
                {{ session('error') }}
            </div>
        @endif

        <div x-show="legacyFallbackConfigured" class="rounded-lg border border-amber-200 dark:border-amber-900/50 bg-amber-50 dark:bg-amber-950/40 px-4 py-3 text-xs text-amber-800 dark:text-amber-300">
            Legacy environment credentials are configured and will be used only when no database account is active.
        </div>

        <!-- Filters Bar (Matches Servers, Users & Environment Variables) -->
        <div class="flex flex-col lg:flex-row gap-4">
            <div class="flex-1 min-w-0">
                <div class="relative rounded-lg">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <x-lucide-search class="h-4 w-4 text-zinc-400 dark:text-zinc-500" />
                    </div>
                    <input type="text" 
                           x-model.debounce.300ms="searchQuery" 
                           class="block w-full pl-10 pr-3 py-2 border border-zinc-200 dark:border-zinc-800 rounded-lg text-sm text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 dark:placeholder-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-900 dark:focus:ring-zinc-100 focus:border-zinc-900 dark:focus:border-zinc-100 bg-white dark:bg-[#18181b]" 
                           placeholder="Search account name, client email, or owner...">
                </div>
            </div>
            
            <div class="flex flex-wrap items-center gap-3">
                <!-- Status Filter Dropdown -->
                <div class="relative" x-data="{ open: false }">
                    <button type="button" 
                            @click="open = !open" 
                            @click.away="open = false" 
                            class="inline-flex items-center justify-between gap-2.5 px-3 py-2 text-sm font-medium border border-zinc-200 dark:border-zinc-800 rounded-lg bg-white dark:bg-[#18181b] text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-800/60 outline-none focus:outline-none focus:ring-0 cursor-pointer min-w-[130px]">
                        <span class="font-medium text-zinc-900 dark:text-zinc-100 capitalize truncate" x-text="statusFilter === 'all' ? 'All Statuses' : (statusFilter === 'active' ? 'Active Only' : 'Inactive Only')"></span>
                        <x-lucide-chevron-down class="size-4 text-zinc-400 dark:text-zinc-500" />
                    </button>
                    <div x-show="open" 
                         style="display: none;" 
                         class="absolute left-0 sm:right-0 sm:left-auto z-50 mt-1 w-44 rounded-md bg-white dark:bg-[#18181b] py-1 border border-zinc-200 dark:border-zinc-800 shadow-lg text-sm focus:outline-none"
                         role="menu">
                        <button type="button" 
                                @click="statusFilter = 'all'; open = false;" 
                                class="flex items-center justify-between w-full px-4 py-2 text-left text-sm text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 cursor-pointer"
                                :class="statusFilter === 'all' ? 'bg-zinc-50 dark:bg-zinc-800/60 font-semibold text-zinc-900 dark:text-zinc-100' : ''"
                                role="menuitem">
                            <span>All Statuses</span>
                            <x-lucide-check x-show="statusFilter === 'all'" class="size-3.5 text-zinc-900 dark:text-zinc-100" />
                        </button>
                        <button type="button" 
                                @click="statusFilter = 'active'; open = false;" 
                                class="flex items-center justify-between w-full px-4 py-2 text-left text-sm text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 cursor-pointer"
                                :class="statusFilter === 'active' ? 'bg-zinc-50 dark:bg-zinc-800/60 font-semibold text-zinc-900 dark:text-zinc-100' : ''"
                                role="menuitem">
                            <span>Active Only</span>
                            <x-lucide-check x-show="statusFilter === 'active'" class="size-3.5 text-zinc-900 dark:text-zinc-100" />
                        </button>
                        <button type="button" 
                                @click="statusFilter = 'inactive'; open = false;" 
                                class="flex items-center justify-between w-full px-4 py-2 text-left text-sm text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 cursor-pointer"
                                :class="statusFilter === 'inactive' ? 'bg-zinc-50 dark:bg-zinc-800/60 font-semibold text-zinc-900 dark:text-zinc-100' : ''"
                                role="menuitem">
                            <span>Inactive Only</span>
                            <x-lucide-check x-show="statusFilter === 'inactive'" class="size-3.5 text-zinc-900 dark:text-zinc-100" />
                        </button>
                    </div>
                </div>

                <!-- Sort By Dropdown -->
                <div class="relative" x-data="{ 
                    open: false,
                    get currentLabel() {
                        const labels = {
                            'newest': 'Sort by Newest',
                            'name': 'Sort by Name',
                            'email': 'Sort by Client Email'
                        };
                        return labels[sortBy] || 'Sort by Newest';
                    }
                }">
                    <button type="button" 
                            @click="open = !open" 
                            @click.away="open = false" 
                            class="inline-flex items-center justify-between gap-2.5 px-3 py-2 text-sm font-medium border border-zinc-200 dark:border-zinc-800 rounded-lg bg-white dark:bg-[#18181b] text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-800/60 outline-none focus:outline-none focus:ring-0 cursor-pointer min-w-[170px]">
                        <span class="font-medium text-zinc-900 dark:text-zinc-100 truncate" x-text="currentLabel"></span>
                        <x-lucide-chevron-down class="size-4 text-zinc-400 dark:text-zinc-500" />
                    </button>
                    <div x-show="open" 
                         style="display: none;" 
                         class="absolute right-0 z-50 mt-1 w-52 rounded-md bg-white dark:bg-[#18181b] py-1 border border-zinc-200 dark:border-zinc-800 shadow-lg text-sm focus:outline-none"
                         role="menu">
                        <button type="button" 
                                @click="sortBy = 'newest'; open = false;" 
                                class="flex items-center justify-between w-full px-4 py-2 text-left text-sm text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 cursor-pointer"
                                :class="sortBy === 'newest' ? 'bg-zinc-50 dark:bg-zinc-800/60 font-semibold text-zinc-900 dark:text-zinc-100' : ''"
                                role="menuitem">
                            <span>Sort by Newest</span>
                            <x-lucide-check x-show="sortBy === 'newest'" class="size-3.5 text-zinc-900 dark:text-zinc-100" />
                        </button>
                        <button type="button" 
                                @click="sortBy = 'name'; open = false;" 
                                class="flex items-center justify-between w-full px-4 py-2 text-left text-sm text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 cursor-pointer"
                                :class="sortBy === 'name' ? 'bg-zinc-50 dark:bg-zinc-800/60 font-semibold text-zinc-900 dark:text-zinc-100' : ''"
                                role="menuitem">
                            <span>Sort by Name</span>
                            <x-lucide-check x-show="sortBy === 'name'" class="size-3.5 text-zinc-900 dark:text-zinc-100" />
                        </button>
                        <button type="button" 
                                @click="sortBy = 'email'; open = false;" 
                                class="flex items-center justify-between w-full px-4 py-2 text-left text-sm text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 cursor-pointer"
                                :class="sortBy === 'email' ? 'bg-zinc-50 dark:bg-zinc-800/60 font-semibold text-zinc-900 dark:text-zinc-100' : ''"
                                role="menuitem">
                            <span>Sort by Client Email</span>
                            <x-lucide-check x-show="sortBy === 'email'" class="size-3.5 text-zinc-900 dark:text-zinc-100" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Accounts List Table (Matches Users & Servers pattern) -->
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
                                <!-- Account Info (Icon, Name, Badge, Email, Owner) -->
                                <div class="flex items-center gap-4 flex-1 min-w-0 pr-4">
                                    <div class="flex-shrink-0 size-9 rounded-lg bg-zinc-100 dark:bg-zinc-800 border border-zinc-200/80 dark:border-zinc-700/80 flex items-center justify-center text-zinc-600 dark:text-zinc-300">
                                        <x-lucide-globe class="h-4.5 w-4.5 text-blue-600 dark:text-blue-400" />
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

                                            <!-- Edit Account -->
                                            <button type="button" 
                                                    @click="openEditForm(account); open = false;" 
                                                    class="flex items-center gap-2 w-full text-left px-4 py-2 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 cursor-pointer" 
                                                    role="menuitem">
                                                <x-lucide-pencil class="size-4 text-zinc-400 dark:text-zinc-500" />
                                                <span>Edit</span>
                                            </button>

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
    </div>

    <!-- Slide-over Drawer for Add/Edit Service Account -->
    <div x-show="showAccountForm" style="display: none;" class="relative z-50" aria-labelledby="gsc-drawer-title" role="dialog" aria-modal="true">
        <div x-show="showAccountForm" 
             x-transition.opacity 
             class="fixed inset-0 bg-black/40 transition-opacity" 
             @click="closeAccountForm()"></div>

        <div class="fixed inset-0 overflow-hidden">
            <div class="absolute inset-0 overflow-hidden">
                <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10 sm:pl-16">
                    <div x-show="showAccountForm" 
                         x-transition:enter="transform transition ease-in-out duration-200 sm:duration-300" 
                         x-transition:enter-start="translate-x-full" 
                         x-transition:enter-end="translate-x-0" 
                         x-transition:leave="transform transition ease-in-out duration-200 sm:duration-300" 
                         x-transition:leave-start="translate-x-0" 
                         x-transition:leave-end="translate-x-full" 
                         class="pointer-events-auto w-screen max-w-xl">
                        <div class="flex h-full flex-col bg-white dark:bg-zinc-900 border-l border-zinc-200 dark:border-zinc-800 shadow-2xl">
                            <!-- Drawer Header -->
                            <div class="px-6 py-5 flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800">
                                <div>
                                    <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100" id="gsc-drawer-title" x-text="editingId ? 'Edit Service Account' : 'Add Service Account'"></h2>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">The JSON key is encrypted before storage and is never exposed.</p>
                                </div>
                                <button type="button" @click="closeAccountForm()" class="rounded-md text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 focus:outline-none transition p-1 cursor-pointer">
                                    <span class="sr-only">Close panel</span>
                                    <x-lucide-x class="size-4.5" />
                                </button>
                            </div>

                            <!-- Drawer Body -->
                            <div class="flex-1 overflow-y-auto px-6 py-6">
                                <form @submit.prevent="saveAccount()" id="gsc-account-form" class="space-y-5">
                                    <div>
                                        <label for="gsc-name" class="block text-xs font-medium text-zinc-700 dark:text-zinc-300 mb-1.5">
                                            Display Name <span class="text-red-500">*</span>
                                        </label>
                                        <input id="gsc-name" 
                                               type="text" 
                                               x-model="accountForm.name" 
                                               required 
                                               placeholder="e.g. Production Search Console"
                                               class="block w-full rounded-md border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-sm text-zinc-900 dark:text-zinc-100 placeholder:text-zinc-400 dark:placeholder:text-zinc-500 focus:border-zinc-400 dark:focus:border-zinc-500 focus:outline-none transition">
                                    </div>

                                    <div>
                                        <label for="gsc-owner" class="block text-xs font-medium text-zinc-700 dark:text-zinc-300 mb-1.5">
                                            Human Owner Email <span class="text-zinc-400 dark:text-zinc-500">(optional)</span>
                                        </label>
                                        <input id="gsc-owner" 
                                               type="email" 
                                               x-model="accountForm.owner_email" 
                                               placeholder="owner@gmail.com"
                                               class="block w-full rounded-md border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-sm text-zinc-900 dark:text-zinc-100 placeholder:text-zinc-400 dark:placeholder:text-zinc-500 focus:border-zinc-400 dark:focus:border-zinc-500 focus:outline-none transition">
                                    </div>

                                    <div>
                                        <label for="gsc-json" class="block text-xs font-medium text-zinc-700 dark:text-zinc-300 mb-1.5">
                                            Service-account JSON 
                                            <span x-show="editingId" class="text-zinc-400 dark:text-zinc-500">(leave blank to keep current key)</span>
                                            <span x-show="!editingId" class="text-red-500">*</span>
                                        </label>
                                        <textarea id="gsc-json" 
                                                  x-model="accountForm.credentials_json" 
                                                  :required="!editingId" 
                                                  rows="9"
                                                  autocomplete="off" 
                                                  spellcheck="false" 
                                                  placeholder='{"type":"service_account", "project_id": "...", ...}'
                                                  class="block w-full rounded-md border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 font-mono text-xs text-zinc-900 dark:text-zinc-100 placeholder:text-zinc-400 dark:placeholder:text-zinc-500 focus:border-zinc-400 dark:focus:border-zinc-500 focus:outline-none transition"></textarea>
                                    </div>

                                    <div class="pt-2">
                                        <label class="inline-flex items-center gap-2.5 text-xs text-zinc-700 dark:text-zinc-300 cursor-pointer select-none">
                                            <input type="checkbox" 
                                                   x-model="accountForm.is_active" 
                                                   class="rounded border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-900 focus:ring-zinc-500">
                                            <span>Make this the active deployment account</span>
                                        </label>
                                    </div>
                                </form>
                            </div>

                            <!-- Drawer Footer -->
                            <div class="px-6 py-4 border-t border-zinc-100 dark:border-zinc-800 flex items-center justify-end gap-3 bg-zinc-50 dark:bg-zinc-800/40">
                                <button type="button" 
                                        @click="closeAccountForm()" 
                                        :disabled="isSaving"
                                        class="inline-flex items-center gap-1.5 px-4 py-2 border border-zinc-200 dark:border-zinc-700 rounded-md text-sm font-medium text-zinc-700 dark:text-zinc-300 bg-white dark:bg-zinc-900 hover:bg-zinc-50 dark:hover:bg-zinc-800 focus:outline-none transition cursor-pointer disabled:opacity-50">
                                    <x-lucide-x class="size-4 text-zinc-500" />
                                    <span>Cancel</span>
                                </button>
                                <button type="submit" 
                                        form="gsc-account-form"
                                        :disabled="isSaving"
                                        class="inline-flex items-center gap-2 px-4 py-2 border border-transparent dark:border-zinc-700/80 rounded-md text-sm font-medium text-white bg-zinc-900 hover:bg-zinc-800 focus:outline-none transition cursor-pointer disabled:opacity-50 shadow-sm">
                                    <span x-show="isSaving" class="animate-spin inline-block size-3.5 border-2 border-white border-t-transparent rounded-full"></span>
                                    <x-lucide-save x-show="!isSaving" class="size-4" />
                                    <span x-text="isSaving ? 'Saving...' : 'Save Account'"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
