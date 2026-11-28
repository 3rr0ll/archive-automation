@extends('layouts.app')

@section('content')
<div class="h-[calc(100vh-7.5rem)] flex flex-col overflow-hidden" x-data="deploymentLogsPage({{ (int) $deployment->id }}, @js($deployment))">
    <!-- Skeleton Loading State -->
    <div x-show="isLoading" x-cloak class="h-full flex flex-col overflow-hidden">
        <x-skeleton.build-logs-skeleton />
    </div>

    <!-- Main Content -->
    <div x-show="!isLoading" class="h-full flex flex-col min-h-0 space-y-4">
        <!-- Top Breadcrumb & Actions Bar (Fixed shrink-0) -->
        <div class="shrink-0">
            <x-build-logs.header :deployment="$deployment" />
        </div>

        <!-- Metadata Cards Row (Status, Duration, Deployed, Trigger, Server) (Fixed shrink-0) -->
        <div class="shrink-0">
            <x-build-logs.overview />
        </div>

        <!-- Build Logs Terminal Feed & Filters Container (Fills remaining viewport space) -->
        <div class="flex-1 min-h-0 flex flex-col space-y-4">
            <x-build-logs.terminal />
            <div x-show="!hasLoadedSubdomainFolders || subdomainFoldersMeta.total > 0" x-cloak class="shrink-0">
                <template x-if="!hasLoadedSubdomainFolders">
                    <div role="status" class="flex items-center gap-2 rounded-lg border border-zinc-200 bg-white px-4 py-3 text-xs text-zinc-600 dark:border-zinc-800 dark:bg-[#18181b] dark:text-zinc-300">
                        <span aria-hidden="true" class="size-4 rounded-full border-2 border-current border-r-transparent motion-safe:animate-spin"></span>
                        <span>Loading subdomain folders…</span>
                    </div>
                </template>
                <x-build-logs.subdomain-folders />
            </div>
        </div>
    </div>
</div>
@endsection
