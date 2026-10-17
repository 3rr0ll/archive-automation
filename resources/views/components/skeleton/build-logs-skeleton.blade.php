<div class="mx-auto py-4 space-y-6 animate-pulse" aria-hidden="true">
    <!-- Header Skeleton: Breadcrumbs & Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2">
        <div class="flex items-center gap-2">
            <div class="h-4 w-20 bg-zinc-200/80 rounded"></div>
            <div class="h-4 w-2 bg-zinc-200/60 rounded"></div>
            <div class="h-4 w-36 bg-zinc-200/80 rounded"></div>
        </div>

        <div class="flex items-center gap-2.5">
            <div class="h-8 w-24 bg-zinc-200/70 rounded-md"></div>
            <div class="h-8 w-24 bg-zinc-200/70 rounded-md"></div>
        </div>
    </div>

    <!-- Metadata Cards Row (5 Cards) -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
        @for ($i = 0; $i < 5; $i++)
            <div class="bg-white border border-zinc-200 rounded-lg p-3.5 flex items-start gap-3">
                <div class="size-8 rounded-lg bg-zinc-200/70 shrink-0"></div>
                <div class="min-w-0 flex-1 space-y-1.5">
                    <div class="h-2.5 w-14 bg-zinc-200/60 rounded"></div>
                    <div class="h-3.5 w-24 bg-zinc-200/80 rounded"></div>
                </div>
            </div>
        @endfor
    </div>

    <!-- Terminal Box Skeleton -->
    <div class="bg-white border border-zinc-200 rounded-lg overflow-hidden">
        <!-- Toolbar Skeleton -->
        <div class="p-3 bg-zinc-50/80 border-b border-zinc-200 flex flex-col md:flex-row md:items-center justify-between gap-3">
            <div class="flex items-center gap-2.5">
                <div class="h-8 w-44 bg-zinc-200/70 rounded-md"></div>
                <div class="h-8 w-28 bg-zinc-200/70 rounded-md"></div>
            </div>
            <div class="flex items-center gap-2">
                <div class="h-8 w-24 bg-zinc-200/70 rounded-md"></div>
                <div class="h-8 w-24 bg-zinc-200/70 rounded-md"></div>
            </div>
        </div>

        <!-- Terminal Logs Feed Skeleton -->
        <div class="p-4 space-y-3 min-h-[420px]">
            @for ($i = 0; $i < 9; $i++)
                <div class="flex items-center gap-3 py-1">
                    <div class="h-3 w-16 bg-zinc-200/60 rounded shrink-0"></div>
                    <div class="h-4 w-20 bg-zinc-200/80 rounded shrink-0"></div>
                    <div class="h-3 bg-zinc-200/70 rounded" style="width: {{ [45, 65, 80, 55, 70, 90, 60, 75, 50][$i] }}%;"></div>
                </div>
            @endfor
        </div>
    </div>
</div>
