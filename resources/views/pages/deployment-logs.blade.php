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
        <x-build-logs.terminal />
    </div>
</div>
@endsection
