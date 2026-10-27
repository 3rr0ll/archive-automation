@extends('layouts.app')

@section('content')
<div class="mx-auto py-6" x-data="bulkDeletePage()">
    <!-- Skeleton Loading State -->
    <div x-show="isLoading">
        <x-skeleton.bulk-delete-skeleton />
    </div>

    <!-- Main Content -->
    <div x-show="!isLoading" x-cloak class="space-y-6">
        <x-bulk-delete.header />

        <x-bulk-delete.input-card />

        <!-- Skeleton Loading State when searching -->
        <div x-show="isSearching && foundDeployments.length === 0" x-cloak>
            <x-skeleton.bulk-delete-results-skeleton />
        </div>

        <x-bulk-delete.summary-banner />

        <!-- Search Results Section -->
        <div x-show="hasSearched && (!isSearching || foundDeployments.length > 0)" x-cloak class="space-y-6">
            <x-bulk-delete.filters />

            <x-bulk-delete.results-list />

            <x-bulk-delete.missing-domains />
        </div>
    </div>
</div>
@endsection
