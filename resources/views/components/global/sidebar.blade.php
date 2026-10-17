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
                        <x-lucide-archive class="size-6 text-zinc-900" />
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
                <x-lucide-log-out x-show="!isLoggingOut" class="size-4.5" />
                <span x-show="isLoggingOut" x-cloak class="animate-spin inline-block size-4.5 border-2 border-zinc-300 border-t-zinc-600 rounded-full"></span>
            </button>
        </div>
    </x-slot:footer>

    @if ($slot->isNotEmpty())
        {{ $slot }}
    @else
        <!-- Platform Navigation -->
        <li class="px-2 mt-4 mb-1">
            <span class="text-[10px] font-medium text-zinc-500 uppercase tracking-widest">Platform</span>
        </li>

        <li class="mt-1">
            <a href="/dashboard" class="group flex items-center gap-3 px-3 py-2 rounded-md transition-colors text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100">
                <x-lucide-layout-dashboard class="size-4.5 shrink-0" />
                <span class="text-sm font-medium truncate">Dashboard</span>
            </a>
        </li>

        <!-- Settings -->
        <li class="px-2 mt-6 mb-1">
            <span class="text-[10px] font-medium text-zinc-500 uppercase tracking-widest">Settings</span>
        </li>

        <li class="mt-1">
            <a href="/environment-variables" class="group flex items-center gap-3 px-3 py-2 rounded-md transition-colors text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100">
                <x-lucide-terminal class="size-4.5 shrink-0" />
                <span class="text-sm font-medium truncate">Environment Variables</span>
            </a>
        </li>

        <li class="mt-1">
            <a href="/servers" class="group flex items-center gap-3 px-3 py-2 rounded-md transition-colors text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100">
                <x-lucide-server class="size-4.5 shrink-0" />
                <span class="text-sm font-medium truncate">Servers</span>
            </a>
        </li>

        <li class="mt-1">
            <a href="/users" class="group flex items-center gap-3 px-3 py-2 rounded-md transition-colors text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100">
                <x-lucide-users class="size-4.5 shrink-0" />
                <span class="text-sm font-medium truncate">Users</span>
            </a>
        </li>
    @endif
</x-side-bar>