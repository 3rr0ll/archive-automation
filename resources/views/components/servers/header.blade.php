<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
    <div>
        <h1 class="text-2xl font-bold text-zinc-900 tracking-tight">Servers</h1>
        <p class="mt-1 text-sm text-zinc-500">
            Manage your connected servers, server IDs, and IP configurations.
        </p>
    </div>
    <div class="flex items-center gap-3">
        <button type="button" 
                @click="syncFromRunCloud()" 
                :disabled="isSyncing"
                class="inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-white bg-zinc-900 hover:bg-zinc-800 disabled:opacity-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-zinc-900 transition-colors cursor-pointer">
            <x-lucide-refresh-cw class="w-4 h-4 mr-2" ::class="isSyncing ? 'animate-spin' : ''" />
            <span x-text="isSyncing ? 'Syncing...' : 'Sync from RunCloud'"></span>
        </button>
    </div>
</div>
