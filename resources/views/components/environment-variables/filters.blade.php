<div class="flex flex-col lg:flex-row gap-4 mb-6">
    <div class="flex-1 min-w-0">
        <div class="relative rounded-lg">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <x-lucide-search class="h-4 w-4 text-zinc-400" />
            </div>
            <input type="text" class="block w-full pl-10 pr-3 py-2 border border-zinc-200 rounded-lg text-sm text-zinc-900 placeholder-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-900 focus:border-zinc-900" placeholder="Search variables...">
        </div>
    </div>
    
    <div class="flex flex-wrap items-center gap-3">
        <select class="block w-full sm:w-auto pl-3 pr-10 py-2 text-sm border-zinc-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-zinc-900 focus:border-zinc-900">
            <option>All Types</option>
        </select>
        <select class="block w-full sm:w-auto pl-3 pr-10 py-2 text-sm border-zinc-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-zinc-900 focus:border-zinc-900">
            <option>All Environments</option>
        </select>
        <div class="relative flex-1 min-w-[150px]">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <x-lucide-search class="h-4 w-4 text-zinc-400" />
            </div>
            <input type="text" class="block w-full pl-10 pr-3 py-2 border border-zinc-200 rounded-lg text-sm text-zinc-900 placeholder-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-900 focus:border-zinc-900" placeholder="All Editors">
        </div>
        <select class="block w-full sm:w-auto pl-3 pr-10 py-2 text-sm border-zinc-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-zinc-900 focus:border-zinc-900">
            <option>Last Updated</option>
        </select>
    </div>
</div>
