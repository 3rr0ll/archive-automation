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
         class="fixed inset-y-0 left-0 z-50 w-64 bg-[#fafafa] border-r border-zinc-200 flex flex-col md:hidden"
         style="display: none;">
        
        <!-- Brand Header -->
        <div class="flex items-center justify-between px-4 h-14 border-b border-zinc-200 shrink-0">
            <span class="text-sm font-semibold tracking-tight text-zinc-900 truncate">Archive Automation</span>
            <button type="button" @click="mobileOpen = false" class="p-1 rounded-md text-zinc-400 hover:text-zinc-700 hover:bg-zinc-100 transition">
                <x-lucide-x class="size-5" />
            </button>
        </div>

        <!-- Navigation Links -->
        <div class="flex-1 overflow-y-auto px-2 py-4">
            <ul role="list" class="space-y-1">
                <li class="px-2 mb-1">
                    <span class="text-[10px] font-medium text-zinc-500 uppercase tracking-widest">Platform</span>
                </li>
                <li>
                    <a href="/dashboard" class="group flex items-center gap-3 px-3 py-2 rounded-md transition-colors text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100">
                        <x-lucide-layout-dashboard class="size-4.5 shrink-0" />
                        <span class="text-sm font-medium truncate">Dashboard</span>
                    </a>
                </li>

                <li class="px-2 mt-6 mb-1">
                    <span class="text-[10px] font-medium text-zinc-500 uppercase tracking-widest">Settings</span>
                </li>
                <li>
                    <a href="/environment-variables" class="group flex items-center gap-3 px-3 py-2 rounded-md transition-colors text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100">
                        <x-lucide-terminal class="size-4.5 shrink-0" />
                        <span class="text-sm font-medium truncate">Environment Variables</span>
                    </a>
                </li>
                @if(auth()->user()?->isAdmin())
                    <li>
                        <a href="/google-search-console" class="group flex items-center gap-3 px-3 py-2 rounded-md transition-colors text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100">
                            <x-lucide-search class="size-4.5 shrink-0" />
                            <span class="text-sm font-medium truncate">Google Search Console</span>
                        </a>
                    </li>
                @endif
                <li>
                    <a href="/servers" class="group flex items-center gap-3 px-3 py-2 rounded-md transition-colors text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100">
                        <x-lucide-server class="size-4.5 shrink-0" />
                        <span class="text-sm font-medium truncate">Servers</span>
                    </a>
                </li>
                <li>
                    <a href="/users" class="group flex items-center gap-3 px-3 py-2 rounded-md transition-colors text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100">
                        <x-lucide-users class="size-4.5 shrink-0" />
                        <span class="text-sm font-medium truncate">Users</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Mobile User Footer -->
        <div class="px-4 h-14 border-t border-zinc-200 shrink-0 flex items-center justify-between" x-data="userNav">
            <div class="flex items-center gap-3 overflow-hidden">
                <div class="size-8 rounded-md bg-zinc-900 text-white flex items-center justify-center font-semibold text-xs shrink-0" x-text="$store.auth.user.initial"></div>
                <div class="flex flex-col truncate">
                    <span class="text-sm font-semibold text-zinc-900 truncate" x-text="$store.auth.user.name"></span>
                </div>
            </div>
            <button type="button" @click="handleLogout" :disabled="isLoggingOut" class="p-1.5 text-zinc-500 hover:text-zinc-900 hover:bg-zinc-100 rounded-md transition" title="Logout">
                <x-lucide-log-out x-show="!isLoggingOut" class="size-4.5" />
                <span x-show="isLoggingOut" x-cloak class="animate-spin inline-block size-4.5 border-2 border-zinc-300 border-t-zinc-600 rounded-full"></span>
            </button>
        </div>
    </div>

    <!-- Desktop Fixed Sidebar -->
    <div class="hidden md:fixed md:inset-y-0 md:z-40 md:flex md:w-64 md:flex-col border-r border-zinc-200 bg-[#fafafa]">
        <!-- Brand Header -->
        <div class="flex items-center px-4 h-14 border-b border-zinc-200 shrink-0">
            <span class="text-sm font-semibold tracking-tight text-zinc-900 truncate">Archive Automation</span>
        </div>

        <!-- Navigation Links -->
        <div class="flex-1 overflow-y-auto px-2 py-4">
            <ul role="list" class="space-y-1">
                <li class="px-2 mb-1">
                    <span class="text-[10px] font-medium text-zinc-500 uppercase tracking-widest">Platform</span>
                </li>
                <li>
                    <a href="/dashboard" class="group flex items-center gap-3 px-3 py-2 rounded-md transition-colors text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100">
                        <x-lucide-layout-dashboard class="size-4.5 shrink-0" />
                        <span class="text-sm font-medium truncate">Dashboard</span>
                    </a>
                </li>

                <li class="px-2 mt-6 mb-1">
                    <span class="text-[10px] font-medium text-zinc-500 uppercase tracking-widest">Settings</span>
                </li>
                <li>
                    <a href="/environment-variables" class="group flex items-center gap-3 px-3 py-2 rounded-md transition-colors text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100">
                        <x-lucide-terminal class="size-4.5 shrink-0" />
                        <span class="text-sm font-medium truncate">Environment Variables</span>
                    </a>
                </li>
                @if(auth()->user()?->isAdmin())
                    <li>
                        <a href="/google-search-console" class="group flex items-center gap-3 px-3 py-2 rounded-md transition-colors text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100">
                            <x-lucide-search class="size-4.5 shrink-0" />
                            <span class="text-sm font-medium truncate">Google Search Console</span>
                        </a>
                    </li>
                @endif
                <li>
                    <a href="/servers" class="group flex items-center gap-3 px-3 py-2 rounded-md transition-colors text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100">
                        <x-lucide-server class="size-4.5 shrink-0" />
                        <span class="text-sm font-medium truncate">Servers</span>
                    </a>
                </li>
                <li>
                    <a href="/users" class="group flex items-center gap-3 px-3 py-2 rounded-md transition-colors text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100">
                        <x-lucide-users class="size-4.5 shrink-0" />
                        <span class="text-sm font-medium truncate">Users</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Desktop User Footer -->
        <div class="px-4 h-14 border-t border-zinc-200 shrink-0 flex items-center justify-between" x-data="userNav">
            <div class="flex items-center gap-3 overflow-hidden">
                <div class="size-8 rounded-md bg-zinc-900 text-white flex items-center justify-center font-semibold text-xs shrink-0" x-text="$store.auth.user.initial"></div>
                <div class="flex flex-col truncate">
                    <span class="text-sm font-semibold text-zinc-900 truncate" x-text="$store.auth.user.name"></span>
                </div>
            </div>
            <button type="button" @click="handleLogout" :disabled="isLoggingOut" class="p-1.5 text-zinc-500 hover:text-zinc-900 hover:bg-zinc-100 rounded-md transition cursor-pointer" title="Logout">
                <x-lucide-log-out x-show="!isLoggingOut" class="size-4.5" />
                <span x-show="isLoggingOut" x-cloak class="animate-spin inline-block size-4.5 border-2 border-zinc-300 border-t-zinc-600 rounded-full"></span>
            </button>
        </div>
    </div>
</aside>
