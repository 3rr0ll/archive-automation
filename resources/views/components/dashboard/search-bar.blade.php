    <div class="flex items-center gap-4" x-data>
        <div class="relative flex-1">
            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                <svg class="size-4 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
            </div>
            <input type="text" class="block w-full h-9 pl-10 pr-3 text-sm text-zinc-900 border border-zinc-200 rounded-md bg-white focus:ring-zinc-900 focus:border-zinc-900" placeholder="Search Domains...">
        </div>
        <div class="flex items-center gap-3">
            <button class="size-9 flex items-center justify-center border border-zinc-200 rounded-md bg-white text-zinc-600 hover:bg-zinc-50 transition"><svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" /></svg></button>
            
            <div class="h-9 flex items-center p-0.5 border border-zinc-200 rounded-md bg-white">
                <button @click="$store.domainView.set('grid')" :class="$store.domainView.mode === 'grid' ? 'bg-zinc-100 text-zinc-900' : 'text-zinc-500 hover:text-zinc-700'" class="h-full px-2 flex items-center justify-center rounded transition focus:outline-none">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" /></svg>
                </button>
                <button @click="$store.domainView.set('list')" :class="$store.domainView.mode === 'list' ? 'bg-zinc-100 text-zinc-900' : 'text-zinc-500 hover:text-zinc-700'" class="h-full px-2 flex items-center justify-center rounded transition focus:outline-none">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" /></svg>
                </button>
            </div>
        </div>
    </div>
