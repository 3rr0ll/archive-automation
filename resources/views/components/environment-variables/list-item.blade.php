@props(['id', 'name', 'date', 'initial' => 'J'])

<li class="bg-white first:rounded-t-lg last:rounded-b-lg">
    <div class="px-4 py-4 sm:px-6 flex items-center justify-between">
        <div class="flex items-center gap-3 flex-1 min-w-0">
            <div class="flex-shrink-0 text-zinc-400">
                <x-lucide-lock class="h-4 w-4" />
            </div>
            <p class="text-sm font-medium text-zinc-900 font-mono truncate">{{ $name }}</p>
        </div>
        <div class="flex items-center justify-between gap-8 flex-shrink-0">

            <div class="flex items-center gap-4 text-sm text-zinc-500 whitespace-nowrap">
                <span>Added {{ $date }}</span>
                <div class="h-6 w-6 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0">
                    <span class="text-xs text-green-700 font-medium">{{ strtoupper($initial) }}</span>
                </div>
            </div>
            <div class="relative" x-data="{ open: false }">
                <button type="button" @click="open = !open" @click.away="open = false" class="text-zinc-400 hover:text-zinc-600 focus:outline-none rounded p-1 hover:bg-zinc-100 transition-colors">
                    <x-lucide-ellipsis-vertical class="h-4 w-4" />
                </button>
                <div x-show="open" x-transition.opacity style="display: none;" class="absolute right-0 z-10 mt-2 w-48 origin-top-right rounded-md bg-white py-1 border border-zinc-200 focus:outline-none" role="menu" aria-orientation="vertical" tabindex="-1">
                    <button type="button" @click="copyKey('{{ $name }}'); open = false;" class="block w-full text-left px-4 py-2 text-sm text-zinc-700 hover:bg-zinc-100" role="menuitem">Copy Name</button>
                    <button type="button" @click="alert('Edit functionality coming soon'); open = false;" class="block w-full text-left px-4 py-2 text-sm text-zinc-700 hover:bg-zinc-100" role="menuitem">Edit</button>
                    <button type="button" @click="deleteVariable({{ $id }}); open = false;" class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50" role="menuitem">Delete</button>
                </div>
            </div>
        </div>
    </div>
</li>
