<template x-if="deletionSummary">
    <div class="bg-white dark:bg-[#18181b] border border-zinc-200 dark:border-zinc-800 rounded-lg p-4 transition-colors">
        <div class="flex items-start gap-3">
            <div class="size-8 rounded-full flex items-center justify-center shrink-0"
                 :class="deletionSummary.skippedCount > 0 ? 'bg-zinc-100 dark:bg-zinc-800 text-amber-600 dark:text-amber-400' : 'bg-zinc-100 dark:bg-zinc-800 text-emerald-600 dark:text-emerald-400'">
                <x-lucide-check-circle-2 x-show="deletionSummary.skippedCount === 0" class="size-4" />
                <x-lucide-alert-triangle x-show="deletionSummary.skippedCount > 0" class="size-4" />
            </div>
            <div class="space-y-0.5 flex-1 text-xs">
                <p class="font-semibold text-sm text-zinc-900 dark:text-zinc-100">Deletion Process Complete</p>
                <p class="text-zinc-500 dark:text-zinc-400">
                    Deleted <span class="font-semibold text-zinc-900 dark:text-zinc-100" x-text="deletionSummary.processedCount"></span> deployment(s).
                    <template x-if="deletionSummary.skippedCount > 0">
                        <span><span class="font-semibold text-zinc-900 dark:text-zinc-100" x-text="deletionSummary.skippedCount"></span> could not be deleted.</span>
                    </template>
                </p>
            </div>
        </div>
    </div>
</template>
