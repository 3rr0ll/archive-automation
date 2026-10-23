@props(['deployment'])

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2">
    <div class="flex items-center gap-2 text-sm">
        <a href="/dashboard" class="inline-flex items-center gap-1.5 text-zinc-500 hover:text-zinc-900 transition font-medium">
            <x-lucide-arrow-left class="size-4" />
            <span>Dashboard</span>
        </a>
        <span class="text-zinc-300">/</span>
        <div class="flex items-center gap-2">
            <span class="font-semibold text-zinc-900" x-text="deployment ? deployment.domain : '{{ $deployment->domain }}'"></span>
            <span class="text-xs text-zinc-400 font-mono" x-show="deployment?.version" x-text="`(${deployment.version})`"></span>
        </div>
    </div>

    <div class="flex items-center gap-2.5">
        <!-- External Website Link -->
        <a :href="'https://' + (deployment ? deployment.domain : '{{ $deployment->domain }}')" 
           target="_blank" 
           rel="noopener"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-zinc-700 dark:text-zinc-300 bg-white dark:bg-zinc-900 hover:bg-zinc-50 dark:hover:bg-zinc-800 border border-zinc-200 dark:border-zinc-800 rounded-md transition shadow-xs">
            <span>Visit site</span>
            <x-lucide-external-link class="size-3.5 text-zinc-400" />
        </a>

        <!-- Edit Files via SFTP Button -->
        <a :href="'/deployments/' + (deployment ? (deployment.hash || deployment.id) : '{{ $deployment->hash }}') + '/files'"
           x-show="deployment && (deployment.status === 'completed' || deployment.status === 'failed')"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-zinc-700 dark:text-zinc-300 bg-white dark:bg-zinc-900 hover:bg-zinc-50 dark:hover:bg-zinc-800 border border-zinc-200 dark:border-zinc-800 rounded-md transition shadow-xs">
            <x-lucide-file-code class="size-3.5 text-zinc-500 dark:text-zinc-400" />
            <span>Edit files</span>
        </a>

        <!-- Retry Button (if failed or completed) -->
        <button type="button" 
                @click="triggerRetry()"
                :disabled="isRetrying"
                x-show="deployment && (deployment.status === 'failed' || deployment.status === 'completed')"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-white bg-zinc-900 hover:bg-zinc-800 dark:border dark:border-zinc-700/80 disabled:opacity-50 rounded-md transition cursor-pointer shadow-sm">
            <x-lucide-rotate-cw class="size-3.5" x-bind:class="isRetrying ? 'animate-spin' : ''" />
            <span x-text="isRetrying ? 'Retrying...' : 'Redeploy'"></span>
        </button>

        <!-- Cancel Button (if running) -->
        <button type="button" 
                @click="triggerCancel()"
                :disabled="isCancelling"
                x-show="deployment && (deployment.status === 'pending' || deployment.status === 'processing')"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-red-700 dark:text-rose-300/90 bg-red-50 dark:bg-rose-950/40 hover:bg-red-100 dark:hover:bg-rose-950/60 border border-red-200 dark:border-rose-900/40 disabled:opacity-50 rounded-md transition cursor-pointer">
            <x-lucide-ban class="size-3.5 text-red-600 dark:text-rose-400" />
            <span x-text="isCancelling ? 'Cancelling...' : 'Cancel'"></span>
        </button>

        @if(auth()->user()?->isAdmin())
            <!-- Delete locally and from RunCloud (admin only) -->
            <button type="button"
                    @click="triggerDelete()"
                    :disabled="isDeleting"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-red-700 dark:text-rose-300/90 bg-white dark:bg-zinc-900 hover:bg-red-50 dark:hover:bg-rose-950/40 border border-red-200 dark:border-rose-900/40 disabled:opacity-50 rounded-md transition cursor-pointer">
                <x-lucide-trash-2 class="size-3.5 text-red-600 dark:text-rose-400" />
                <span x-text="isDeleting ? 'Deleting...' : 'Delete'"></span>
            </button>
        @endif
    </div>
</div>
