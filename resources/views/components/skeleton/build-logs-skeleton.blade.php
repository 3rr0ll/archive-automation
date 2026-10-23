<div class="h-full flex flex-col min-h-0 space-y-4 animate-pulse select-none" aria-hidden="true">
    <!-- Header Skeleton: Breadcrumbs & Actions -->
    <div class="shrink-0 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2">
        <div class="flex items-center gap-2">
            <div class="h-4 w-20 bg-zinc-200 dark:bg-zinc-800 rounded"></div>
            <div class="h-4 w-2 bg-zinc-200 dark:bg-zinc-800 rounded"></div>
            <div class="h-4 w-40 bg-zinc-200 dark:bg-zinc-800 rounded"></div>
        </div>

        <div class="flex items-center gap-2.5">
            <div class="h-8 w-24 bg-zinc-200 dark:bg-zinc-800 rounded-md"></div>
            <div class="h-8 w-24 bg-zinc-200 dark:bg-zinc-800 rounded-md"></div>
        </div>
    </div>

    <!-- Metadata Grid Skeleton (Matches 6 items: Status, Duration, Deployed, Trigger, Source, Server) -->
    <div class="shrink-0 py-2">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-y-6 gap-x-8">
            @for ($i = 0; $i < 6; $i++)
                <div class="flex items-start gap-3.5">
                    <div class="size-8 rounded bg-zinc-200 dark:bg-zinc-800 shrink-0"></div>
                    <div class="min-w-0 flex-1 space-y-1.5">
                        <div class="h-3 w-16 bg-zinc-200/80 dark:bg-zinc-800/80 rounded"></div>
                        <div class="h-4 w-28 bg-zinc-200 dark:bg-zinc-800 rounded"></div>
                    </div>
                </div>
            @endfor
        </div>
    </div>

    <!-- Terminal Box Skeleton (Matches flex-1 min-h-0 of real terminal) -->
    <div class="flex-1 min-h-0 flex flex-col bg-white dark:bg-[#18181b] border border-zinc-200 dark:border-zinc-800 rounded-lg overflow-hidden transition-colors">
        <!-- Toolbar Skeleton (Matches actual build-logs toolbar) -->
        <div class="p-3 bg-zinc-50/60 dark:bg-[#18181b] border-b border-zinc-200 dark:border-zinc-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shrink-0">
            <div class="flex items-center gap-2 flex-1">
                <!-- Dropdown skeleton -->
                <div class="h-8 w-24 bg-zinc-200 dark:bg-zinc-800 rounded-md shrink-0"></div>
                <!-- Search input skeleton -->
                <div class="h-8 w-full max-w-sm bg-zinc-200 dark:bg-zinc-800 rounded-md"></div>
            </div>
            <!-- Right controls skeleton -->
            <div class="flex items-center gap-2 shrink-0">
                <div class="h-3 w-16 bg-zinc-200 dark:bg-zinc-800 rounded"></div>
                <div class="h-4 w-px bg-zinc-200 dark:bg-zinc-800"></div>
                <div class="h-8 w-24 bg-zinc-200 dark:bg-zinc-800 rounded-md"></div>
                <div class="h-8 w-20 bg-zinc-200 dark:bg-zinc-800 rounded-md"></div>
            </div>
        </div>

        <!-- Terminal Logs Feed Skeleton -->
        <div class="flex-1 min-h-0 p-4 space-y-3 bg-white dark:bg-[#121214] overflow-hidden">
            @for ($i = 0; $i < 9; $i++)
                <div class="flex items-center gap-3 py-1">
                    <div class="h-3 w-16 bg-zinc-200/60 dark:bg-zinc-800/60 rounded shrink-0"></div>
                    <div class="h-4 w-20 bg-zinc-200/80 dark:bg-zinc-800/80 rounded shrink-0"></div>
                    <div class="h-3 bg-zinc-200/70 dark:bg-zinc-800/70 rounded" style="width: {{ [45, 65, 80, 55, 70, 90, 60, 75, 50][$i] }}%;"></div>
                </div>
            @endfor
        </div>
    </div>
</div>
