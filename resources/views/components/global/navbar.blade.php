<header class="sticky top-0 z-30 h-14 bg-[#fafafa]/90 backdrop-blur-md border-b border-zinc-200 flex items-center">
    <div class="w-full px-4 sm:px-6 lg:px-8 flex justify-between items-center">
        <!-- Left: Mobile controls or Brand Name -->
        <div class="flex items-center gap-3">
            @if(auth()->user()?->isAdmin())
                <!-- Mobile Hamburger (Admin only, since admin has drawer) -->
                <button type="button" @click="$dispatch('open-sidebar')" class="md:hidden p-1.5 text-zinc-500 hover:text-zinc-900 rounded-md hover:bg-zinc-200/50 transition cursor-pointer" aria-label="Open sidebar">
                    <x-lucide-menu class="size-5" />
                </button>
            @else
                <!-- Brand for standard users -->
                <a href="/dashboard" class="flex items-center gap-2 text-zinc-900 hover:text-zinc-700 transition">
                    <span class="text-sm font-semibold tracking-tight text-zinc-900">Archive Automation</span>
                </a>
            @endif
        </div>

        <!-- Right: User Icon + Dropdown -->
        <div class="relative" x-data="{ open: false, ...userNav() }">
            <button type="button" 
                    @click="open = !open" 
                    class="relative flex items-center justify-center size-8 rounded-full bg-zinc-900 text-white font-semibold text-xs hover:ring-2 hover:ring-zinc-950/20 hover:ring-offset-2 transition-all focus:outline-hidden cursor-pointer select-none"
                    id="user-menu-button"
                    aria-expanded="false"
                    aria-haspopup="true">
                <span x-text="$store.auth.user.initial"></span>
            </button>

            <!-- Dropdown Menu -->
            <div x-show="open" 
                 @click.outside="open = false"
                 @keydown.escape.window="open = false"
                 x-transition:enter="transition ease-out duration-100"
                 x-transition:enter-start="transform opacity-0 scale-95"
                 x-transition:enter-end="transform opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-75"
                 x-transition:leave-start="transform opacity-100 scale-100"
                 x-transition:leave-end="transform opacity-0 scale-95"
                 class="absolute right-0 z-50 mt-2 w-56 origin-top-right rounded-lg bg-white p-1.5 shadow-lg border border-zinc-200 focus:outline-hidden"
                 style="display: none;"
                 role="menu"
                 aria-orientation="vertical"
                 tabindex="-1">
                
                <!-- User Profile Summary Header -->
                <div class="px-3 py-2 border-b border-zinc-100 mb-1">
                    <p class="text-xs font-semibold text-zinc-900 truncate" x-text="$store.auth.user.name"></p>
                    <p class="text-[11px] text-zinc-500 truncate" x-text="$store.auth.user.email"></p>
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-zinc-100 text-zinc-700 capitalize mt-1.5" x-text="$store.auth.user.role"></span>
                </div>

                <!-- Menu Item: Logout -->
                <button type="button" 
                        @click="handleLogout" 
                        :disabled="isLoggingOut"
                        class="w-full flex items-center gap-2 px-3 py-2 text-xs font-medium text-red-600 hover:bg-red-50 rounded-md transition-colors text-left cursor-pointer disabled:opacity-50"
                        role="menuitem">
                    <span x-show="isLoggingOut" x-cloak class="animate-spin inline-block size-3.5 border-2 border-red-300 border-t-red-600 rounded-full shrink-0"></span>
                    <span x-text="isLoggingOut ? 'Logging out...' : 'Log out'"></span>
                </button>
            </div>
        </div>
    </div>
</header>