@extends('layouts.app')

@section('content')
<div class="space-y-8" x-data="dashboardOverview">

    <!-- Top Search Bar -->
    <x-dashboard.search-bar />

    <!-- Main Grid Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <!-- Left Column: Queue & Failed Domains -->
        <div class="lg:col-span-4 xl:col-span-3 space-y-8">
            <x-dashboard.queue />
            <x-dashboard.failed-domains />
        </div>

        <!-- Right Column: Domains (Successful) -->
        <x-dashboard.domain-list />

    </div>
</div>
@endsection