<!-- Domain List Loading Skeleton (Grid & List) -->
<div x-show="isFetchingDeployments" class="animate-pulse select-none" aria-hidden="true">
    <!-- Grid View Skeleton -->
    <template x-if="$store.domainView.mode === 'grid'">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @for ($i = 0; $i < 9; $i++)
                <div class="bg-white dark:bg-[#18181b] border border-zinc-200 dark:border-zinc-800 rounded-lg p-4 flex flex-col justify-between min-h-[140px]">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="size-8 rounded-full bg-zinc-200/80 dark:bg-zinc-800 shrink-0"></div>
                            <div class="space-y-1.5 min-w-0">
                                <div class="h-3.5 w-28 bg-zinc-200/80 dark:bg-zinc-800 rounded"></div>
                                <div class="h-2.5 w-36 bg-zinc-200/60 dark:bg-zinc-800/80 rounded"></div>
                            </div>
                        </div>
                        <div class="size-4 rounded bg-zinc-200/70 dark:bg-zinc-800 shrink-0"></div>
                    </div>
                    <div class="mt-4 pt-3 border-t border-zinc-100 dark:border-zinc-800 flex items-center justify-between">
                        <div class="h-3 w-20 bg-zinc-200/70 dark:bg-zinc-800 rounded"></div>
                        <div class="h-3 w-12 bg-zinc-200/60 dark:bg-zinc-800/80 rounded"></div>
                    </div>
                </div>
            @endfor
        </div>
    </template>

    <!-- List View Skeleton -->
    <template x-if="$store.domainView.mode === 'list'">
        <div class="bg-white dark:bg-[#18181b] border border-zinc-200 dark:border-zinc-800 rounded-lg overflow-hidden divide-y divide-zinc-200 dark:divide-zinc-800">
            @for ($i = 0; $i < 9; $i++)
                <div class="flex items-center justify-between p-4">
                    <div class="flex items-center gap-4 min-w-0">
                        <div class="size-8 rounded-full bg-zinc-200/80 dark:bg-zinc-800 shrink-0"></div>
                        <div class="space-y-1.5">
                            <div class="h-3.5 w-32 bg-zinc-200/80 dark:bg-zinc-800 rounded"></div>
                            <div class="h-2.5 w-48 bg-zinc-200/60 dark:bg-zinc-800/80 rounded"></div>
                        </div>
                    </div>
                    <div class="flex items-center gap-6 shrink-0">
                        <div class="hidden sm:block space-y-1.5 text-right">
                            <div class="h-3 w-24 bg-zinc-200/80 dark:bg-zinc-800 rounded ml-auto"></div>
                            <div class="h-2.5 w-28 bg-zinc-200/60 dark:bg-zinc-800/80 rounded ml-auto"></div>
                        </div>
                        <div class="size-4 rounded bg-zinc-200/70 dark:bg-zinc-800"></div>
                    </div>
                </div>
            @endfor
        </div>
    </template>
</div>
