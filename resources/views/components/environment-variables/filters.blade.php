<div class="flex flex-col lg:flex-row gap-4 mb-6">
    <div class="flex-1 min-w-0">
        <div class="relative rounded-lg">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <x-lucide-search class="h-4 w-4 text-zinc-400" />
            </div>
            <input type="text" 
                   x-model.debounce.300ms="searchQuery" 
                   class="block w-full pl-10 pr-3 py-2 border border-zinc-200 rounded-lg text-sm text-zinc-900 placeholder-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-900 focus:border-zinc-900 bg-white" 
                   placeholder="Search variables...">
        </div>
    </div>
    
    <div class="flex flex-wrap items-center gap-3">
        <!-- Custom Type Filter Dropdown -->
        <div class="relative" x-data="{ open: false }">
            <button type="button" 
                    @click="open = !open" 
                    @click.away="open = false" 
                    class="inline-flex items-center justify-between gap-2.5 px-3 py-2 text-sm font-medium border border-zinc-200 rounded-lg bg-white text-zinc-700 hover:bg-zinc-50 outline-none focus:outline-none focus:ring-0 cursor-pointer min-w-[130px]">
                <span class="font-medium text-zinc-900 capitalize truncate" x-text="typeFilter === 'all' ? 'All Types' : typeFilter"></span>
                <x-lucide-chevron-down class="size-4 text-zinc-400" />
            </button>
            <div x-show="open" 
                 style="display: none;" 
                 class="absolute left-0 sm:right-0 sm:left-auto z-50 mt-1 w-44 rounded-md bg-white py-1 border border-zinc-200 shadow-lg text-sm focus:outline-none"
                 role="menu">
                <button type="button" 
                        @click="typeFilter = 'all'; open = false;" 
                        class="flex items-center justify-between w-full px-4 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-100 cursor-pointer"
                        :class="typeFilter === 'all' ? 'bg-zinc-50 font-semibold text-zinc-900' : ''"
                        role="menuitem">
                    <span>All Types</span>
                    <x-lucide-check x-show="typeFilter === 'all'" class="size-3.5 text-zinc-900" />
                </button>
                <button type="button" 
                        @click="typeFilter = 'secret'; open = false;" 
                        class="flex items-center justify-between w-full px-4 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-100 cursor-pointer"
                        :class="typeFilter === 'secret' ? 'bg-zinc-50 font-semibold text-zinc-900' : ''"
                        role="menuitem">
                    <span>Secret</span>
                    <x-lucide-check x-show="typeFilter === 'secret'" class="size-3.5 text-zinc-900" />
                </button>
                <button type="button" 
                        @click="typeFilter = 'config'; open = false;" 
                        class="flex items-center justify-between w-full px-4 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-100 cursor-pointer"
                        :class="typeFilter === 'config' ? 'bg-zinc-50 font-semibold text-zinc-900' : ''"
                        role="menuitem">
                    <span>Config</span>
                    <x-lucide-check x-show="typeFilter === 'config'" class="size-3.5 text-zinc-900" />
                </button>
            </div>
        </div>

        <!-- Custom Sort By / Last Updated Dropdown -->
        <div class="relative" x-data="{ 
            open: false,
            get currentLabel() {
                const labels = {
                    'updated_desc': 'Last Updated (Newest)',
                    'updated_asc': 'Last Updated (Oldest)',
                    'created_desc': 'Date Added (Newest)',
                    'created_asc': 'Date Added (Oldest)',
                    'key_asc': 'Name (A-Z)',
                    'key_desc': 'Name (Z-A)'
                };
                return labels[sortBy] || 'Sort By';
            }
        }">
            <button type="button" 
                    @click="open = !open" 
                    @click.away="open = false" 
                    class="inline-flex items-center justify-between gap-2.5 px-3 py-2 text-sm font-medium border border-zinc-200 rounded-lg bg-white text-zinc-700 hover:bg-zinc-50 outline-none focus:outline-none focus:ring-0 cursor-pointer min-w-[190px]">
                <span class="font-medium text-zinc-900 truncate" x-text="currentLabel"></span>
                <x-lucide-chevron-down class="size-4 text-zinc-400" />
            </button>
            <div x-show="open" 
                 style="display: none;" 
                 class="absolute right-0 z-50 mt-1 w-56 rounded-md bg-white py-1 border border-zinc-200 shadow-lg text-sm focus:outline-none"
                 role="menu">
                <button type="button" 
                        @click="sortBy = 'updated_desc'; open = false;" 
                        class="flex items-center justify-between w-full px-4 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-100 cursor-pointer"
                        :class="sortBy === 'updated_desc' ? 'bg-zinc-50 font-semibold text-zinc-900' : ''"
                        role="menuitem">
                    <span>Last Updated (Newest)</span>
                    <x-lucide-check x-show="sortBy === 'updated_desc'" class="size-3.5 text-zinc-900" />
                </button>
                <button type="button" 
                        @click="sortBy = 'updated_asc'; open = false;" 
                        class="flex items-center justify-between w-full px-4 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-100 cursor-pointer"
                        :class="sortBy === 'updated_asc' ? 'bg-zinc-50 font-semibold text-zinc-900' : ''"
                        role="menuitem">
                    <span>Last Updated (Oldest)</span>
                    <x-lucide-check x-show="sortBy === 'updated_asc'" class="size-3.5 text-zinc-900" />
                </button>
                <div class="border-t border-zinc-100 my-1"></div>
                <button type="button" 
                        @click="sortBy = 'created_desc'; open = false;" 
                        class="flex items-center justify-between w-full px-4 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-100 cursor-pointer"
                        :class="sortBy === 'created_desc' ? 'bg-zinc-50 font-semibold text-zinc-900' : ''"
                        role="menuitem">
                    <span>Date Added (Newest)</span>
                    <x-lucide-check x-show="sortBy === 'created_desc'" class="size-3.5 text-zinc-900" />
                </button>
                <button type="button" 
                        @click="sortBy = 'created_asc'; open = false;" 
                        class="flex items-center justify-between w-full px-4 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-100 cursor-pointer"
                        :class="sortBy === 'created_asc' ? 'bg-zinc-50 font-semibold text-zinc-900' : ''"
                        role="menuitem">
                    <span>Date Added (Oldest)</span>
                    <x-lucide-check x-show="sortBy === 'created_asc'" class="size-3.5 text-zinc-900" />
                </button>
                <div class="border-t border-zinc-100 my-1"></div>
                <button type="button" 
                        @click="sortBy = 'key_asc'; open = false;" 
                        class="flex items-center justify-between w-full px-4 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-100 cursor-pointer"
                        :class="sortBy === 'key_asc' ? 'bg-zinc-50 font-semibold text-zinc-900' : ''"
                        role="menuitem">
                    <span>Name (A-Z)</span>
                    <x-lucide-check x-show="sortBy === 'key_asc'" class="size-3.5 text-zinc-900" />
                </button>
                <button type="button" 
                        @click="sortBy = 'key_desc'; open = false;" 
                        class="flex items-center justify-between w-full px-4 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-100 cursor-pointer"
                        :class="sortBy === 'key_desc' ? 'bg-zinc-50 font-semibold text-zinc-900' : ''"
                        role="menuitem">
                    <span>Name (Z-A)</span>
                    <x-lucide-check x-show="sortBy === 'key_desc'" class="size-3.5 text-zinc-900" />
                </button>
            </div>
        </div>
    </div>
</div>
