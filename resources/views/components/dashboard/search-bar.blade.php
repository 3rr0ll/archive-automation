    <div class="flex items-center gap-4" x-data>
        <div class="relative flex-1">
            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                <x-lucide-search class="size-4 text-zinc-400" />
            </div>
            <input type="text" class="block w-full h-9 pl-10 pr-3 text-sm text-zinc-900 border border-zinc-200 rounded-md bg-white focus:ring-zinc-900 focus:border-zinc-900" placeholder="Search Domains...">
        </div>
        <div class="flex items-center gap-3">
            <button class="size-9 flex items-center justify-center border border-zinc-200 rounded-md bg-white text-zinc-600 hover:bg-zinc-50 transition" title="Filter">
                <x-lucide-filter class="size-4" />
            </button>
            
            <div class="h-9 flex items-center p-0.5 border border-zinc-200 rounded-md bg-white">
                <button @click="$store.domainView.set('grid')" :class="$store.domainView.mode === 'grid' ? 'bg-zinc-100 text-zinc-900' : 'text-zinc-500 hover:text-zinc-700'" class="h-full px-2 flex items-center justify-center rounded transition focus:outline-none" title="Grid view">
                    <x-lucide-layout-grid class="size-4" />
                </button>
                <button @click="$store.domainView.set('list')" :class="$store.domainView.mode === 'list' ? 'bg-zinc-100 text-zinc-900' : 'text-zinc-500 hover:text-zinc-700'" class="h-full px-2 flex items-center justify-center rounded transition focus:outline-none" title="List view">
                    <x-lucide-list class="size-4" />
                </button>
            </div>
        </div>
    </div>
