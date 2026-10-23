<div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
    <div class="relative flex-1">
        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
            <x-lucide-search class="size-4 text-zinc-400" />
        </div>
        <input type="text" 
               x-model.debounce.300ms="searchQuery" 
               @input="currentPage = 1"
               class="block w-full h-9 pl-10 pr-3 text-sm text-zinc-900 border border-zinc-200 rounded-md bg-white focus:ring-zinc-900 focus:border-zinc-900 transition placeholder:text-zinc-400" 
               placeholder="Search Domains...">
    </div>
    
    <div class="flex items-center gap-2.5 shrink-0 justify-between sm:justify-end">
        <!-- View Toggle Grid / List -->
        <div class="h-9 flex items-center p-0.5 border border-zinc-200 rounded-md bg-white">
            <button type="button" @click="$store.domainView.set('grid')" :class="$store.domainView.mode === 'grid' ? 'bg-zinc-100 text-zinc-900' : 'text-zinc-500 hover:text-zinc-700'" class="h-full px-2.5 flex items-center justify-center rounded transition focus:outline-none" title="Grid view">
                <x-lucide-layout-grid class="size-4" />
            </button>
            <button type="button" @click="$store.domainView.set('list')" :class="$store.domainView.mode === 'list' ? 'bg-zinc-100 text-zinc-900' : 'text-zinc-500 hover:text-zinc-700'" class="h-full px-2.5 flex items-center justify-center rounded transition focus:outline-none" title="List view">
                <x-lucide-list class="size-4" />
            </button>
        </div>

        <!-- Add / New Deployment Button (opens Drawer) -->
        <button type="button" 
                @click="openModal()" 
                class="h-9 inline-flex items-center gap-1.5 px-3.5 text-xs font-medium rounded-md text-white bg-zinc-900 hover:bg-zinc-800 transition shadow-sm cursor-pointer shrink-0">
            <x-lucide-plus class="size-3.5" />
            <span>New Deployment</span>
        </button>
    </div>
</div>

