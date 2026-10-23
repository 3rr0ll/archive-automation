<div class="flex flex-col lg:flex-row gap-4 mb-6">
    <div class="flex-1 min-w-0">
        <div class="relative rounded-lg">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <x-lucide-search class="h-4 w-4 text-zinc-400 dark:text-zinc-500" />
            </div>
            <input type="text" 
                   x-model.debounce.300ms="searchQuery" 
                   @input="currentPage = 1"
                   class="block w-full pl-10 pr-3 py-2 border border-zinc-200 dark:border-zinc-800 rounded-lg text-sm text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 dark:placeholder-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-900 dark:focus:ring-zinc-100 focus:border-zinc-900 dark:focus:border-zinc-100 bg-white dark:bg-[#18181b]" 
                   placeholder="Search user by name, email, or role...">
        </div>
    </div>
    
    <div class="flex flex-wrap items-center gap-3">
        <!-- Role Filter Dropdown -->
        <div class="relative" x-data="{ open: false }">
            <button type="button" 
                    @click="open = !open" 
                    @click.away="open = false" 
                    class="inline-flex items-center justify-between gap-2.5 px-3 py-2 text-sm font-medium border border-zinc-200 dark:border-zinc-800 rounded-lg bg-white dark:bg-[#18181b] text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-800/60 outline-none focus:outline-none focus:ring-0 cursor-pointer min-w-[130px]">
                <span class="font-medium text-zinc-900 dark:text-zinc-100 capitalize truncate" x-text="roleFilter === 'all' ? 'All Roles' : (roleFilter === 'admin' ? 'Admin Only' : 'User Only')"></span>
                <x-lucide-chevron-down class="size-4 text-zinc-400 dark:text-zinc-500" />
            </button>
            <div x-show="open" 
                 style="display: none;" 
                 class="absolute left-0 sm:right-0 sm:left-auto z-50 mt-1 w-44 rounded-md bg-white dark:bg-[#18181b] py-1 border border-zinc-200 dark:border-zinc-800 shadow-lg text-sm focus:outline-none"
                 role="menu">
                <button type="button" 
                        @click="roleFilter = 'all'; currentPage = 1; open = false;" 
                        class="flex items-center justify-between w-full px-4 py-2 text-left text-sm text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 cursor-pointer"
                        :class="roleFilter === 'all' ? 'bg-zinc-50 dark:bg-zinc-800/60 font-semibold text-zinc-900 dark:text-zinc-100' : ''"
                        role="menuitem">
                    <span>All Roles</span>
                    <x-lucide-check x-show="roleFilter === 'all'" class="size-3.5 text-zinc-900 dark:text-zinc-100" />
                </button>
                <button type="button" 
                        @click="roleFilter = 'admin'; currentPage = 1; open = false;" 
                        class="flex items-center justify-between w-full px-4 py-2 text-left text-sm text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 cursor-pointer"
                        :class="roleFilter === 'admin' ? 'bg-zinc-50 dark:bg-zinc-800/60 font-semibold text-zinc-900 dark:text-zinc-100' : ''"
                        role="menuitem">
                    <span>Admin Only</span>
                    <x-lucide-check x-show="roleFilter === 'admin'" class="size-3.5 text-zinc-900 dark:text-zinc-100" />
                </button>
                <button type="button" 
                        @click="roleFilter = 'user'; currentPage = 1; open = false;" 
                        class="flex items-center justify-between w-full px-4 py-2 text-left text-sm text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 cursor-pointer"
                        :class="roleFilter === 'user' ? 'bg-zinc-50 dark:bg-zinc-800/60 font-semibold text-zinc-900 dark:text-zinc-100' : ''"
                        role="menuitem">
                    <span>User Only</span>
                    <x-lucide-check x-show="roleFilter === 'user'" class="size-3.5 text-zinc-900 dark:text-zinc-100" />
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
                    'email': 'Sort by Email',
                    'oldest': 'Sort by Oldest'
                };
                return labels[sortBy] || 'Sort by Newest';
            }
        }">
            <button type="button" 
                    @click="open = !open" 
                    @click.away="open = false" 
                    class="inline-flex items-center justify-between gap-2.5 px-3 py-2 text-sm font-medium border border-zinc-200 dark:border-zinc-800 rounded-lg bg-white dark:bg-[#18181b] text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-800/60 outline-none focus:outline-none focus:ring-0 cursor-pointer min-w-[160px]">
                <span class="font-medium text-zinc-900 dark:text-zinc-100 truncate" x-text="currentLabel"></span>
                <x-lucide-chevron-down class="size-4 text-zinc-400 dark:text-zinc-500" />
            </button>
            <div x-show="open" 
                 style="display: none;" 
                 class="absolute right-0 z-50 mt-1 w-48 rounded-md bg-white dark:bg-[#18181b] py-1 border border-zinc-200 dark:border-zinc-800 shadow-lg text-sm focus:outline-none"
                 role="menu">
                <button type="button" 
                        @click="sortBy = 'newest'; currentPage = 1; open = false;" 
                        class="flex items-center justify-between w-full px-4 py-2 text-left text-sm text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 cursor-pointer"
                        :class="sortBy === 'newest' ? 'bg-zinc-50 dark:bg-zinc-800/60 font-semibold text-zinc-900 dark:text-zinc-100' : ''"
                        role="menuitem">
                    <span>Sort by Newest</span>
                    <x-lucide-check x-show="sortBy === 'newest'" class="size-3.5 text-zinc-900 dark:text-zinc-100" />
                </button>
                <button type="button" 
                        @click="sortBy = 'name'; currentPage = 1; open = false;" 
                        class="flex items-center justify-between w-full px-4 py-2 text-left text-sm text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 cursor-pointer"
                        :class="sortBy === 'name' ? 'bg-zinc-50 dark:bg-zinc-800/60 font-semibold text-zinc-900 dark:text-zinc-100' : ''"
                        role="menuitem">
                    <span>Sort by Name</span>
                    <x-lucide-check x-show="sortBy === 'name'" class="size-3.5 text-zinc-900 dark:text-zinc-100" />
                </button>
                <button type="button" 
                        @click="sortBy = 'email'; currentPage = 1; open = false;" 
                        class="flex items-center justify-between w-full px-4 py-2 text-left text-sm text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 cursor-pointer"
                        :class="sortBy === 'email' ? 'bg-zinc-50 dark:bg-zinc-800/60 font-semibold text-zinc-900 dark:text-zinc-100' : ''"
                        role="menuitem">
                    <span>Sort by Email</span>
                    <x-lucide-check x-show="sortBy === 'email'" class="size-3.5 text-zinc-900 dark:text-zinc-100" />
                </button>
                <button type="button" 
                        @click="sortBy = 'oldest'; currentPage = 1; open = false;" 
                        class="flex items-center justify-between w-full px-4 py-2 text-left text-sm text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 cursor-pointer"
                        :class="sortBy === 'oldest' ? 'bg-zinc-50 dark:bg-zinc-800/60 font-semibold text-zinc-900 dark:text-zinc-100' : ''"
                        role="menuitem">
                    <span>Sort by Oldest</span>
                    <x-lucide-check x-show="sortBy === 'oldest'" class="size-3.5 text-zinc-900 dark:text-zinc-100" />
                </button>
            </div>
        </div>
    </div>
</div>
