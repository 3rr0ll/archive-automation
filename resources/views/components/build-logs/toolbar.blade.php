<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3 border-b border-zinc-200 dark:border-zinc-800 bg-zinc-50/60 dark:bg-[#18181b] transition-colors">
    <div class="flex items-center gap-2 flex-1">
        <!-- Status Filter Dropdown -->
        <div class="relative shrink-0">
            <select x-model="statusFilter" class="h-8 pl-2.5 pr-7 text-xs font-medium text-zinc-700 dark:text-zinc-200 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700/80 rounded-md focus:outline-none focus:border-zinc-400 dark:focus:border-zinc-500 cursor-pointer">
                <option value="all">All logs</option>
                <option value="success">Success</option>
                <option value="in_progress">In Progress</option>
                <option value="error">Errors</option>
                <option value="warning">Warnings</option>
            </select>
        </div>

        <!-- Search Input -->
        <div class="relative flex-1 max-w-sm">
            <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none">
                <x-lucide-search class="size-3.5 text-zinc-400" />
            </div>
            <input type="text" 
                   x-model="searchQuery" 
                   placeholder="Search logs..." 
                   class="w-full h-8 pl-8 pr-3 text-xs text-zinc-900 dark:text-zinc-100 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700/80 rounded-md placeholder:text-zinc-400 dark:placeholder:text-zinc-500 focus:outline-none focus:border-zinc-400 dark:focus:border-zinc-500">
        </div>
    </div>

    <!-- Right Controls: Auto-scroll, Copy, Log count -->
    <div class="flex items-center gap-2 shrink-0">
        <span class="text-xs text-zinc-500 dark:text-zinc-400 font-medium" x-text="`${filteredLogs.length} event(s)`"></span>

        <div class="h-4 w-px bg-zinc-200 dark:bg-zinc-800"></div>

        <button type="button" 
                @click="autoScroll = !autoScroll"
                :class="autoScroll ? 'bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900' : 'bg-white dark:bg-zinc-900 text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700/80 hover:bg-zinc-50 dark:hover:bg-zinc-800'"
                class="h-8 px-2.5 rounded-md text-xs font-medium transition cursor-pointer flex items-center gap-1.5"
                title="Toggle auto-scroll to latest log">
            <x-lucide-arrow-down-to-line class="size-3.5" />
            <span class="hidden sm:inline">Auto-scroll</span>
        </button>

        <button type="button" 
                @click="copyAllLogs()"
                class="h-8 px-2.5 rounded-md text-xs font-medium text-zinc-700 dark:text-zinc-300 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700/80 hover:bg-zinc-50 dark:hover:bg-zinc-800 transition cursor-pointer flex items-center gap-1.5"
                title="Copy logs">
            <x-lucide-copy class="size-3.5 text-zinc-500 dark:text-zinc-400" />
            <span class="hidden sm:inline">Copy</span>
        </button>
    </div>
</div>
