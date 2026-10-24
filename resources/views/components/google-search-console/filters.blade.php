<div class="flex flex-col lg:flex-row gap-4 mb-6">
    <div class="flex-1 min-w-0">
        <div class="relative rounded-lg">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <x-lucide-search class="h-4 w-4 text-zinc-400 dark:text-zinc-500" />
            </div>
            <input type="text" 
                   x-model.debounce.300ms="searchQuery" 
                   class="block w-full pl-10 pr-3 py-2 border border-zinc-200 dark:border-zinc-800 rounded-lg text-sm text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 dark:placeholder-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-900 dark:focus:ring-zinc-100 focus:border-zinc-900 dark:focus:border-zinc-100 bg-white dark:bg-[#18181b]" 
                   placeholder="Search account name, client email, or owner...">
        </div>
    </div>
    
    <div class="flex flex-wrap items-center gap-3">
        <!-- Status Filter Dropdown -->
        <div class="relative" x-data="{ open: false }">
            <button type="button" 
                    @click="open = !open" 
                    @click.away="open = false" 
                    class="inline-flex items-center justify-between gap-2.5 px-3 py-2 text-sm font-medium border border-zinc-200 dark:border-zinc-800 rounded-lg bg-white dark:bg-[#18181b] text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-800/60 outline-none focus:outline-none focus:ring-0 cursor-pointer min-w-[130px]">
                <span class="font-medium text-zinc-900 dark:text-zinc-100 capitalize truncate" x-text="statusFilter === 'all' ? 'All Statuses' : (statusFilter === 'active' ? 'Active Only' : 'Inactive Only')"></span>
                <x-lucide-chevron-down class="size-4 text-zinc-400 dark:text-zinc-500" />
            </button>
            <div x-show="open" 
                 style="display: none;" 
                 class="absolute left-0 sm:right-0 sm:left-auto z-50 mt-1 w-44 rounded-md bg-white dark:bg-[#18181b] py-1 border border-zinc-200 dark:border-zinc-800 shadow-lg text-sm focus:outline-none"
                 role="menu">
                <button type="button" 
                        @click="statusFilter = 'all'; open = false;" 
                        class="flex items-center justify-between w-full px-4 py-2 text-left text-sm text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 cursor-pointer"
                        :class="statusFilter === 'all' ? 'bg-zinc-50 dark:bg-zinc-800/60 font-semibold text-zinc-900 dark:text-zinc-100' : ''"
                        role="menuitem">
                    <span>All Statuses</span>
                    <x-lucide-check x-show="statusFilter === 'all'" class="size-3.5 text-zinc-900 dark:text-zinc-100" />
                </button>
                <button type="button" 
                        @click="statusFilter = 'active'; open = false;" 
                        class="flex items-center justify-between w-full px-4 py-2 text-left text-sm text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 cursor-pointer"
                        :class="statusFilter === 'active' ? 'bg-zinc-50 dark:bg-zinc-800/60 font-semibold text-zinc-900 dark:text-zinc-100' : ''"
                        role="menuitem">
                    <span>Active Only</span>
                    <x-lucide-check x-show="statusFilter === 'active'" class="size-3.5 text-zinc-900 dark:text-zinc-100" />
                </button>
                <button type="button" 
                        @click="statusFilter = 'inactive'; open = false;" 
                        class="flex items-center justify-between w-full px-4 py-2 text-left text-sm text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 cursor-pointer"
                        :class="statusFilter === 'inactive' ? 'bg-zinc-50 dark:bg-zinc-800/60 font-semibold text-zinc-900 dark:text-zinc-100' : ''"
                        role="menuitem">
                    <span>Inactive Only</span>
                    <x-lucide-check x-show="statusFilter === 'inactive'" class="size-3.5 text-zinc-900 dark:text-zinc-100" />
                </button>
            </div>
        </div>

        <!-- Sort By Dropdown -->
        <div class="relative" x-data="{ 
            open: false,
            get currentLabel() {
                const labels = {
                    'newest': 'Sort by Newest',
                    'name': 'Sort by Name',
                    'email': 'Sort by Client Email'
                };
                return labels[sortBy] || 'Sort by Newest';
            }
        }">
            <button type="button" 
                    @click="open = !open" 
                    @click.away="open = false" 
                    class="inline-flex items-center justify-between gap-2.5 px-3 py-2 text-sm font-medium border border-zinc-200 dark:border-zinc-800 rounded-lg bg-white dark:bg-[#18181b] text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-800/60 outline-none focus:outline-none focus:ring-0 cursor-pointer min-w-[170px]">
                <span class="font-medium text-zinc-900 dark:text-zinc-100 truncate" x-text="currentLabel"></span>
                <x-lucide-chevron-down class="size-4 text-zinc-400 dark:text-zinc-500" />
            </button>
            <div x-show="open" 
                 style="display: none;" 
                 class="absolute right-0 z-50 mt-1 w-52 rounded-md bg-white dark:bg-[#18181b] py-1 border border-zinc-200 dark:border-zinc-800 shadow-lg text-sm focus:outline-none"
                 role="menu">
                <button type="button" 
                        @click="sortBy = 'newest'; open = false;" 
                        class="flex items-center justify-between w-full px-4 py-2 text-left text-sm text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 cursor-pointer"
                        :class="sortBy === 'newest' ? 'bg-zinc-50 dark:bg-zinc-800/60 font-semibold text-zinc-900 dark:text-zinc-100' : ''"
                        role="menuitem">
                    <span>Sort by Newest</span>
                    <x-lucide-check x-show="sortBy === 'newest'" class="size-3.5 text-zinc-900 dark:text-zinc-100" />
                </button>
                <button type="button" 
                        @click="sortBy = 'name'; open = false;" 
                        class="flex items-center justify-between w-full px-4 py-2 text-left text-sm text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 cursor-pointer"
                        :class="sortBy === 'name' ? 'bg-zinc-50 dark:bg-zinc-800/60 font-semibold text-zinc-900 dark:text-zinc-100' : ''"
                        role="menuitem">
                    <span>Sort by Name</span>
                    <x-lucide-check x-show="sortBy === 'name'" class="size-3.5 text-zinc-900 dark:text-zinc-100" />
                </button>
                <button type="button" 
                        @click="sortBy = 'email'; open = false;" 
                        class="flex items-center justify-between w-full px-4 py-2 text-left text-sm text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 cursor-pointer"
                        :class="sortBy === 'email' ? 'bg-zinc-50 dark:bg-zinc-800/60 font-semibold text-zinc-900 dark:text-zinc-100' : ''"
                        role="menuitem">
                    <span>Sort by Client Email</span>
                    <x-lucide-check x-show="sortBy === 'email'" class="size-3.5 text-zinc-900 dark:text-zinc-100" />
                </button>
            </div>
        </div>
    </div>
</div>
