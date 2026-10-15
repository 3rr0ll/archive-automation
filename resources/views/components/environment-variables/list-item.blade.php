@props(['id', 'name', 'type', 'environments', 'note' => null, 'date', 'initial' => 'J'])

<li class="bg-white first:rounded-t-lg last:rounded-b-lg">
    <div class="px-4 py-4 sm:px-6 flex items-center justify-between">
        <div class="flex items-center gap-3 flex-1 min-w-0">
            <div class="flex-shrink-0 text-zinc-400">
                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
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
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M6 10a2 2 0 11-4 0 2 2 0 014 0zM12 10a2 2 0 11-4 0 2 2 0 014 0zM16 12a2 2 0 100-4 2 2 0 000 4z" />
                    </svg>
                </button>
                <div x-show="open" x-transition.opacity style="display: none;" class="absolute right-0 z-10 mt-2 w-48 origin-top-right rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none" role="menu" aria-orientation="vertical" tabindex="-1">
                    <button type="button" @click="navigator.clipboard.writeText('{{ $name }}'); open = false; alert('Copied to clipboard!')" class="block w-full text-left px-4 py-2 text-sm text-zinc-700 hover:bg-zinc-100" role="menuitem">Copy Name</button>
                    <button
                        type="button"
                        @click="$dispatch('edit-environment-variable', { id: {{ (int) $id }}, key: @js($name), type: @js($type), environments: @js($environments), note: @js($note) }); open = false"
                        class="block w-full text-left px-4 py-2 text-sm text-zinc-700 hover:bg-zinc-100"
                        role="menuitem"
                    >Edit</button>
                    <button type="button" @click="if(confirm('Are you sure you want to delete this?')) { fetch('/api/environment-variables/{{ $id }}', { method: 'DELETE', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' } }).then(res => { if(res.ok) window.location.reload(); else alert('Failed to delete'); }) }" class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50" role="menuitem">Delete</button>
                </div>
            </div>
        </div>
    </div>
</li>
