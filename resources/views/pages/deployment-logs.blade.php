@extends('layouts.app')

@section('content')
<div class="mx-auto py-4 space-y-6" x-data="deploymentLogsPage({{ (int) $deployment->id }}, @js($deployment))">
    <!-- Skeleton Loading State -->
    <div x-show="isLoading" x-cloak>
        <x-skeleton.build-logs-skeleton />
    </div>

    <!-- Main Content -->
    <div x-show="!isLoading" class="space-y-6">
        <!-- Top Breadcrumb & Actions Bar -->
        <x-build-logs.header :deployment="$deployment" />

        <!-- Metadata Cards Row (Status, Duration, Deployed, Trigger, Server) -->
        <x-build-logs.overview />

        <!-- Build Logs Terminal Feed & Filters Container -->
        <x-build-logs.terminal />
    </div>
</div>
@endsection
