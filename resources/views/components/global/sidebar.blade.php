<aside class="relative" x-data="{ mobileOpen: false }" @open-sidebar.window="mobileOpen = true">
    <!-- Mobile Backdrop -->
    <div x-show="mobileOpen"
         x-transition:enter="transition-opacity ease-linear duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-linear duration-300"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="mobileOpen = false"
         class="fixed inset-0 bg-zinc-900/40 backdrop-blur-xs z-40 md:hidden"
         style="display: none;"></div>

    <!-- Mobile Sidebar Drawer -->
    <div x-show="mobileOpen"
         x-transition:enter="transition ease-in-out duration-300 transform"
         x-transition:enter-start="-translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in-out duration-300 transform"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="-translate-x-full"
         @keydown.escape.window="mobileOpen = false"
         class="fixed inset-y-0 left-0 z-50 w-64 bg-[#fafafa] dark:bg-[#121214] border-r border-zinc-200 dark:border-zinc-800 flex flex-col md:hidden transition-colors"
         style="display: none;">
        
        <!-- Brand Header -->
        <div class="flex items-center justify-between px-4 h-14 border-b border-zinc-200 dark:border-zinc-800 shrink-0">
            <span class="text-sm font-semibold tracking-tight text-zinc-900 dark:text-zinc-100 truncate">Archive Automation</span>
            <button type="button" @click="mobileOpen = false" class="p-1 rounded-md text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition">
                <x-lucide-x class="size-5" />
            </button>
        </div>

        <!-- Navigation Links -->
        <div class="flex-1 overflow-y-auto px-2 py-4">
            <ul role="list" class="space-y-1">
                <li class="px-2 mb-1">
                    <span class="text-[10px] font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-widest">Platform</span>
                </li>
                <li>
                    <a href="/dashboard" class="group flex items-center gap-3 px-3 py-2 rounded-md transition-colors text-zinc-600 dark:text-zinc-300 hover:text-zinc-900 dark:hover:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-800/60">
                        <x-lucide-layout-dashboard class="size-4.5 shrink-0" />
                        <span class="text-sm font-medium truncate">Dashboard</span>
                    </a>
                </li>

                <li class="px-2 mt-6 mb-1">
                    <span class="text-[10px] font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-widest">Settings</span>
                </li>
                <li>
                    <a href="/environment-variables" class="group flex items-center gap-3 px-3 py-2 rounded-md transition-colors text-zinc-600 dark:text-zinc-300 hover:text-zinc-900 dark:hover:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-800/60">
                        <x-lucide-terminal class="size-4.5 shrink-0" />
                        <span class="text-sm font-medium truncate">Environment Variables</span>
                    </a>
                </li>
                @if(auth()->user()?->isAdmin())
                    <li>
                        <a href="/google-search-console" class="group flex items-center gap-3 px-3 py-2 rounded-md transition-colors text-zinc-600 dark:text-zinc-300 hover:text-zinc-900 dark:hover:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-800/60">
                            <x-lucide-search class="size-4.5 shrink-0" />
                            <span class="text-sm font-medium truncate">Google Search Console</span>
                        </a>
                    </li>
                @endif
                <li>
                    <a href="/servers" class="group flex items-center gap-3 px-3 py-2 rounded-md transition-colors text-zinc-600 dark:text-zinc-300 hover:text-zinc-900 dark:hover:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-800/60">
                        <x-lucide-server class="size-4.5 shrink-0" />
                        <span class="text-sm font-medium truncate">Servers</span>
                    </a>
                </li>
                <li>
                    <a href="/users" class="group flex items-center gap-3 px-3 py-2 rounded-md transition-colors text-zinc-600 dark:text-zinc-300 hover:text-zinc-900 dark:hover:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-800/60">
                        <x-lucide-users class="size-4.5 shrink-0" />
                        <span class="text-sm font-medium truncate">Users</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Desktop Fixed Sidebar -->
    <div class="hidden md:fixed md:inset-y-0 md:z-40 md:flex md:w-64 md:flex-col border-r border-zinc-200 dark:border-zinc-800 bg-[#fafafa] dark:bg-[#121214] transition-all duration-200"
         :class="{ 'sidebar-collapsed-nav': $store.sidebar?.collapsed }">
        <!-- Brand Header -->
        <div class="flex items-center h-14 border-b border-zinc-200 dark:border-zinc-800 shrink-0 transition-all duration-200"
             :class="$store.sidebar?.collapsed ? 'px-0 justify-center' : 'px-4'">
            <template x-if="!$store.sidebar?.collapsed">
                <span class="text-sm font-semibold tracking-tight text-zinc-900 dark:text-zinc-100 truncate">Archive Automation</span>
            </template>
            <template x-if="$store.sidebar?.collapsed">
                <span class="text-sm font-bold tracking-tight text-zinc-900 dark:text-zinc-100">AA</span>
            </template>
        </div>

        <!-- Navigation Links -->
        <div class="flex-1 overflow-y-auto overflow-x-hidden px-2 py-4">
            <ul role="list" class="space-y-1">
                <li class="px-2 mb-1" x-show="!$store.sidebar?.collapsed">
                    <span class="text-[10px] font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-widest">Platform</span>
                </li>
                <li>
                    <a href="/dashboard"
                       :title="$store.sidebar?.collapsed ? 'Dashboard' : ''"
                       class="group flex items-center rounded-md transition-colors text-zinc-600 dark:text-zinc-300 hover:text-zinc-900 dark:hover:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-800/60"
                       :class="$store.sidebar?.collapsed ? 'justify-center p-2.5' : 'gap-3 px-3 py-2'">
                        <x-lucide-layout-dashboard class="size-4.5 shrink-0" />
                        <span x-show="!$store.sidebar?.collapsed" class="text-sm font-medium truncate">Dashboard</span>
                    </a>
                </li>
                @if(auth()->user()?->isAdmin())
                    <li class="px-2 mt-6 mb-1" x-show="!$store.sidebar?.collapsed">
                        <span class="text-[10px] font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-widest">Settings</span>
                    </li>
                    <li class="my-2 border-t border-zinc-200 dark:border-zinc-800/80" x-show="$store.sidebar?.collapsed"></li>
                    <li>
                        <a href="/environment-variables"
                           :title="$store.sidebar?.collapsed ? 'Environment Variables' : ''"
                           class="group flex items-center rounded-md transition-colors text-zinc-600 dark:text-zinc-300 hover:text-zinc-900 dark:hover:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-800/60"
                           :class="$store.sidebar?.collapsed ? 'justify-center p-2.5' : 'gap-3 px-3 py-2'">
                            <x-lucide-terminal class="size-4.5 shrink-0" />
                            <span x-show="!$store.sidebar?.collapsed" class="text-sm font-medium truncate">Environment Variables</span>
                        </a>
                    </li>
                    <li>
                        <a href="/google-search-console"
                           :title="$store.sidebar?.collapsed ? 'Google Search Console' : ''"
                           class="group flex items-center rounded-md transition-colors text-zinc-600 dark:text-zinc-300 hover:text-zinc-900 dark:hover:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-800/60"
                           :class="$store.sidebar?.collapsed ? 'justify-center p-2.5' : 'gap-3 px-3 py-2'">
                            <x-lucide-search class="size-4.5 shrink-0" />
                            <span x-show="!$store.sidebar?.collapsed" class="text-sm font-medium truncate">Google Search Console</span>
                        </a>
                    </li>
                    <li>
                        <a href="/servers"
                           :title="$store.sidebar?.collapsed ? 'Servers' : ''"
                           class="group flex items-center rounded-md transition-colors text-zinc-600 dark:text-zinc-300 hover:text-zinc-900 dark:hover:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-800/60"
                           :class="$store.sidebar?.collapsed ? 'justify-center p-2.5' : 'gap-3 px-3 py-2'">
                            <x-lucide-server class="size-4.5 shrink-0" />
                            <span x-show="!$store.sidebar?.collapsed" class="text-sm font-medium truncate">Servers</span>
                        </a>
                    </li>
                    <li>
                        <a href="/users"
                           :title="$store.sidebar?.collapsed ? 'Users' : ''"
                           class="group flex items-center rounded-md transition-colors text-zinc-600 dark:text-zinc-300 hover:text-zinc-900 dark:hover:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-800/60"
                           :class="$store.sidebar?.collapsed ? 'justify-center p-2.5' : 'gap-3 px-3 py-2'">
                            <x-lucide-users class="size-4.5 shrink-0" />
                            <span x-show="!$store.sidebar?.collapsed" class="text-sm font-medium truncate">Users</span>
                        </a>
                    </li>
                @endif
            </ul>
        </div>

        <!-- Sidebar Footer with Collapse/Expand Toggle (Icon only, left-aligned when expanded) -->
        <div class="border-t border-zinc-200 dark:border-zinc-800 p-2 shrink-0 bg-[#fafafa] dark:bg-[#121214] transition-colors flex items-center"
             :class="$store.sidebar?.collapsed ? 'justify-center' : 'justify-start px-3'">
            <button type="button"
                    @click="$store.sidebar?.toggle()"
                    class="p-2 rounded-md text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-800/60 transition cursor-pointer select-none inline-flex items-center justify-center"
                    :title="$store.sidebar?.collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                    aria-label="Toggle sidebar collapse">
                <span class="inline-flex items-center justify-center transition-transform duration-200"
                      :class="$store.sidebar?.collapsed ? 'rotate-180' : ''">
                    <x-lucide-panel-left-close class="size-4.5 shrink-0" />
                </span>
            </button>
        </div>
    </div>
</aside>
