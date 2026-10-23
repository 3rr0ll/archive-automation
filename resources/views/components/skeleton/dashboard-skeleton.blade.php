<div class="space-y-8 animate-pulse select-none" aria-hidden="true">
    <!-- Top Search & Actions Bar Skeleton -->
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
        <div class="h-9 flex-1 bg-zinc-200/70 dark:bg-zinc-800 rounded-md"></div>
        <div class="flex items-center gap-2.5 shrink-0 justify-between sm:justify-end">
            <div class="h-9 w-20 bg-zinc-200/70 dark:bg-zinc-800 rounded-md"></div>
            <div class="h-9 w-32 bg-zinc-200/70 dark:bg-zinc-800 rounded-md"></div>
        </div>
    </div>

    <!-- Main Grid Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        <!-- Left Column: Queue & Failed Domains Skeleton -->
        <div class="lg:col-span-4 xl:col-span-3 space-y-8">
            <!-- Queue Card -->
            <div>
                <div class="h-4 w-16 bg-zinc-200/80 dark:bg-zinc-800 rounded mb-3"></div>
                <div class="bg-white dark:bg-[#18181b] border border-zinc-200 dark:border-zinc-800 rounded-lg p-4 space-y-4 transition-colors">
                    <div class="flex items-center justify-between pb-1 border-b border-zinc-100 dark:border-zinc-800">
                        <div class="h-3.5 w-20 bg-zinc-200/70 dark:bg-zinc-800 rounded"></div>
                        <div class="h-4 w-6 bg-zinc-200/70 dark:bg-zinc-800 rounded-full"></div>
                    </div>
                    <!-- Queue items mock -->
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="size-4 rounded-full bg-zinc-200/70 dark:bg-zinc-800"></div>
                                <div class="h-3.5 w-28 bg-zinc-200/70 dark:bg-zinc-800 rounded"></div>
                            </div>
                            <div class="h-3 w-14 bg-zinc-200/60 dark:bg-zinc-800/80 rounded"></div>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="size-4 rounded-full bg-zinc-200/70 dark:bg-zinc-800"></div>
                                <div class="h-3.5 w-24 bg-zinc-200/70 dark:bg-zinc-800 rounded"></div>
                            </div>
                            <div class="h-3 w-14 bg-zinc-200/60 dark:bg-zinc-800/80 rounded"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Failed Domains Card -->
            <div>
                <div class="h-4 w-28 bg-zinc-200/80 dark:bg-zinc-800 rounded mb-3"></div>
                <div class="bg-white dark:bg-[#18181b] border border-zinc-200 dark:border-zinc-800 rounded-lg p-4 space-y-3 transition-colors">
                    <div class="flex items-center justify-between">
                        <div class="h-3.5 w-28 bg-zinc-200/70 dark:bg-zinc-800 rounded"></div>
                        <div class="h-4 w-12 bg-zinc-200/60 dark:bg-zinc-800/80 rounded"></div>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="h-3.5 w-32 bg-zinc-200/70 dark:bg-zinc-800 rounded"></div>
                        <div class="h-4 w-12 bg-zinc-200/60 dark:bg-zinc-800/80 rounded"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Domains (List View) Skeleton -->
        <div class="lg:col-span-8 xl:col-span-9">
            <div class="flex items-center justify-between mb-4">
                <div class="h-4 w-36 bg-zinc-200/80 dark:bg-zinc-800 rounded"></div>
            </div>

            <!-- List View Skeleton -->
            <div class="bg-white dark:bg-[#18181b] border border-zinc-200 dark:border-zinc-800 rounded-lg overflow-hidden divide-y divide-zinc-200 dark:divide-zinc-800 transition-colors">
                @for ($i = 0; $i < 6; $i++)
                    <div class="flex items-center justify-between p-4">
                        <!-- Left Section -->
                        <div class="flex items-center gap-4 min-w-0">
                            <div class="size-8 rounded-full bg-zinc-200/80 dark:bg-zinc-800 shrink-0"></div>
                            <div class="space-y-1.5">
                                <div class="h-4 w-32 bg-zinc-200/80 dark:bg-zinc-800 rounded"></div>
                                <div class="h-3 w-44 bg-zinc-200/60 dark:bg-zinc-800/80 rounded"></div>
                            </div>
                        </div>
                        <!-- Right Section -->
                        <div class="flex items-center gap-6 shrink-0">
                            <div class="hidden sm:block space-y-1.5 text-right">
                                <div class="h-3.5 w-24 bg-zinc-200/80 dark:bg-zinc-800 rounded ml-auto"></div>
                                <div class="h-3 w-28 bg-zinc-200/60 dark:bg-zinc-800/80 rounded ml-auto"></div>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="h-5 w-16 bg-zinc-200/70 dark:bg-zinc-800 rounded"></div>
                                <div class="size-7 rounded-md bg-zinc-200/60 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700"></div>
                            </div>
                        </div>
                    </div>
                @endfor
            </div>
        </div>
    </div>
</div>
