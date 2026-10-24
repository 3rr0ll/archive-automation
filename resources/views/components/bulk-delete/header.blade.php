<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
    <div>
        <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 tracking-tight">Bulk Delete</h1>
        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
            Search and delete deployed domains across all servers in bulk.
        </p>
    </div>
    <div class="flex items-center gap-3">
        <button type="button"
                x-show="hasSearched && foundDeployments.length > 0"
                @click="confirmDeleteSelected()"
                :disabled="selectedIds.length === 0 || isDeleting"
                class="inline-flex items-center justify-center gap-2 px-4 py-2 border border-transparent dark:border-zinc-700/80 text-sm font-medium rounded-lg text-white bg-red-600 hover:bg-red-700 dark:bg-rose-600 dark:hover:bg-rose-700 disabled:opacity-50 focus:outline-none transition-colors shadow-sm cursor-pointer">
            <span x-show="isDeleting" class="animate-spin inline-block size-3.5 border-2 border-white border-t-transparent rounded-full mr-1"></span>
            <x-lucide-trash-2 x-show="!isDeleting" class="size-4" />
            <span x-text="isDeleting ? 'Deleting' : `Delete Selected (${selectedIds.length})`"></span>
        </button>
    </div>
</div>
