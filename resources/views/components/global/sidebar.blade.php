@props([
    'collapsible' => false,
    'smart' => true,
    'navigate' => false,
    'brand' => null,
    'brandCollapsed' => null,
    'footer' => null,
])

<x-side-bar :collapsible="$collapsible"
            :smart="$smart"
            :navigate="$navigate"
            {{ $attributes }}>
    @if ($brand !== false)
        <x-slot:brand>
            @if ($brand)
                {{ $brand }}
            @else
                <div class="flex items-center gap-3 px-4 h-14 border-b border-zinc-200">
                    <div class="flex flex-col min-w-0">
                        <span class="text-sm font-semibold tracking-tight text-zinc-900 truncate">Archive Automation</span>
                    </div>
                </div>
            @endif
        </x-slot:brand>
    @endif

    @if ($brandCollapsed !== false && $collapsible)
        <x-slot:brand-collapsed>
            @if ($brandCollapsed)
                {{ $brandCollapsed }}
            @else
                <div class="flex items-center justify-center h-14 border-b border-zinc-200">
                    <div class="flex items-center justify-center shrink-0" title="Archive">
                        <svg class="size-6 text-zinc-900" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2L2 22h20L12 2z" />
                        </svg>
                    </div>
                </div>
            @endif
        </x-slot:brand-collapsed>
    @endif

    <x-slot:footer>
        <script>
            if (document.currentScript && document.currentScript.parentElement) {
                const footerParent = document.currentScript.parentElement;
                footerParent.style.borderTopWidth = '0px';
                footerParent.style.padding = '0px';
                
                // The sidebar wrapper has a default pb-4 that pushes our footer up. Let's remove it.
                if (footerParent.parentElement) {
                    footerParent.parentElement.style.paddingBottom = '0px';
                }
            }
        </script>
        <div class="px-4 h-14 w-full flex items-center border-t border-zinc-200" :class="railed ? 'justify-center' : 'justify-between'" x-data="userNav">
            <div class="flex items-center gap-3 overflow-hidden" x-show="!railed">
                <div class="size-8 rounded-md bg-zinc-900 text-white flex items-center justify-center font-semibold text-xs shrink-0" x-text="$store.auth.user.initial">
                </div>
                <div class="flex flex-col truncate">
                    <span class="text-sm font-semibold text-zinc-900 truncate" x-text="$store.auth.user.name"></span>
                </div>
            </div>
            
            <button type="button"
                    @click="handleLogout"
                    :disabled="isLoggingOut"
                    class="p-1.5 text-zinc-500 hover:text-zinc-900 hover:bg-zinc-100 rounded-md transition focus:outline-none shrink-0"
                    title="Logout">
                <svg x-show="!isLoggingOut" class="size-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                <span x-show="isLoggingOut" x-cloak class="animate-spin inline-block size-4.5 border-2 border-zinc-300 border-t-zinc-600 rounded-full"></span>
            </button>
        </div>
    </x-slot:footer>

    @if ($slot->isNotEmpty())
        {{ $slot }}
    @else
        <!-- Platform Navigation -->
        <li class="px-2 mt-4 mb-1" x-show="!railed">
            <span class="text-[10px] font-medium text-zinc-500 uppercase tracking-widest">Platform</span>
        </li>
        <li x-show="railed" class="mt-4 mb-2 mx-auto w-4 border-t border-zinc-200"></li>

        <li class="mt-1">
            <a href="/dashboard" class="group flex items-center gap-3 px-3 py-2 rounded-md transition-colors text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100">
                <svg class="size-4.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" /></svg>
                <span class="text-sm font-medium truncate" x-show="!railed">Dashboard</span>
            </a>
        </li>

        <!-- Administration -->
        <li class="px-2 mt-6 mb-1" x-show="!railed">
            <span class="text-[10px] font-medium text-zinc-500 uppercase tracking-widest">Settings</span>
        </li>
        <li x-show="railed" class="mt-6 mb-2 mx-auto w-4 border-t border-zinc-200"></li>

        <li class="mt-1">
            <a href="/api-credentials" class="group flex items-center gap-3 px-3 py-2 rounded-md transition-colors text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100">
                <svg class="size-4.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" /></svg>
                <span class="text-sm font-medium truncate" x-show="!railed">API Credentials</span>
            </a>
        </li>

        <li class="mt-1">
            <a href="/servers" class="group flex items-center gap-3 px-3 py-2 rounded-md transition-colors text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100">
                <svg class="size-4.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" /></svg>
                <span class="text-sm font-medium truncate" x-show="!railed">Servers</span>
            </a>
        </li>

        <li class="mt-1">
            <a href="/users" class="group flex items-center gap-3 px-3 py-2 rounded-md transition-colors text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100">
                <svg class="size-4.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                <span class="text-sm font-medium truncate" x-show="!railed">Users</span>
            </a>
        </li>
    @endif
</x-side-bar>