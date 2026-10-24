<div class="space-y-6 animate-pulse select-none" aria-hidden="true">
    <!-- Filters Bar Skeleton -->
    <div class="flex flex-col lg:flex-row gap-4">
        <div class="h-9 flex-1 bg-zinc-200/70 dark:bg-zinc-800 rounded-lg"></div>
        <div class="flex items-center gap-3">
            <div class="h-9 w-32 bg-zinc-200/70 dark:bg-zinc-800 rounded-lg shrink-0"></div>
            <div class="h-9 w-36 bg-zinc-200/70 dark:bg-zinc-800 rounded-lg shrink-0"></div>
        </div>
    </div>

    <!-- Results List Skeleton -->
    <div class="bg-white dark:bg-[#18181b] border border-zinc-200 dark:border-zinc-800 rounded-lg overflow-hidden divide-y divide-zinc-200 dark:divide-zinc-800 transition-colors">
        <!-- Top bar skeleton -->
        <div class="px-4 py-3 bg-zinc-50/50 dark:bg-zinc-900/40 border-b border-zinc-200 dark:border-zinc-800 flex items-center justify-between">
            <div class="h-4 w-36 bg-zinc-200/80 dark:bg-zinc-800 rounded"></div>
            <div class="h-4 w-16 bg-zinc-200/60 dark:bg-zinc-800/80 rounded"></div>
        </div>

        @for ($i = 0; $i < 5; $i++)
            <div class="px-4 py-4 sm:px-6 flex items-center justify-between">
                <div class="flex items-center gap-3.5 flex-1 min-w-0 pr-4">
                    <div class="size-4 rounded bg-zinc-200/70 dark:bg-zinc-800 shrink-0"></div>
                    <div class="size-9 rounded-full bg-zinc-200/80 dark:bg-zinc-800 shrink-0"></div>
                    <div class="flex-1 min-w-0 space-y-1.5">
                        <div class="flex items-center gap-2.5">
                            <div class="h-4 w-36 bg-zinc-200/80 dark:bg-zinc-800 rounded"></div>
                            <div class="h-4 w-16 bg-zinc-200/60 dark:bg-zinc-800/80 rounded"></div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="h-3 w-24 bg-zinc-200/60 dark:bg-zinc-800/80 rounded"></div>
                            <div class="h-3 w-28 bg-zinc-200/60 dark:bg-zinc-800/80 rounded"></div>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-6 shrink-0">
                    <div class="size-6 rounded bg-zinc-200/60 dark:bg-zinc-800"></div>
                </div>
            </div>
        @endfor
    </div>
</div>
