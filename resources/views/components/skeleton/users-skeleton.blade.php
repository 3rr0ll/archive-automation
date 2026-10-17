<div class="mx-auto py-6 space-y-6 animate-pulse" aria-hidden="true">
    <!-- Header Skeleton -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="space-y-1.5">
            <div class="h-6 w-28 bg-zinc-200/80 rounded"></div>
            <div class="h-3.5 w-64 bg-zinc-200/60 rounded"></div>
        </div>
        <div class="flex items-center gap-2.5">
            <div class="h-9 w-28 bg-zinc-200/70 rounded-md"></div>
        </div>
    </div>

    <!-- Filters Bar Skeleton -->
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
        <div class="h-9 flex-1 bg-zinc-200/70 rounded-md"></div>
        <div class="h-9 w-32 bg-zinc-200/70 rounded-md shrink-0"></div>
    </div>

    <!-- Users List Skeleton -->
    <div class="bg-white border border-zinc-200 rounded-lg overflow-hidden divide-y divide-zinc-200">
        @for ($i = 0; $i < 5; $i++)
            <div class="px-4 py-4 sm:px-6 flex items-center justify-between">
                <div class="flex items-center gap-3.5 flex-1 min-w-0 pr-4">
                    <div class="size-9 rounded-full bg-zinc-200/80 shrink-0"></div>
                    <div class="flex-1 min-w-0 space-y-1.5">
                        <div class="flex items-center gap-2">
                            <div class="h-4 w-28 bg-zinc-200/80 rounded"></div>
                            <div class="h-4 w-14 bg-zinc-200/60 rounded"></div>
                        </div>
                        <div class="h-3 w-40 bg-zinc-200/60 rounded"></div>
                    </div>
                </div>
                <div class="flex items-center gap-6 shrink-0">
                    <div class="hidden sm:block">
                        <div class="h-3 w-24 bg-zinc-200/60 rounded"></div>
                    </div>
                    <div class="size-6 rounded bg-zinc-200/60"></div>
                </div>
            </div>
        @endfor
    </div>
</div>
