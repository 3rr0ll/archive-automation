@extends('layouts.app')

@section('content')
<div class="mx-auto py-6" x-data="googleSearchConsolePage(@js($accounts ?? []), @js($legacyFallback ?? false))" x-init="init()">
    <!-- Skeleton Loading State -->
    <div x-show="isLoading">
        <x-skeleton.google-search-console-skeleton />
    </div>

    <!-- Main Content -->
    <div x-show="!isLoading" x-cloak>
        <x-google-search-console.header />

        <x-google-search-console.filters />

        <x-google-search-console.list />
    </div>
</div>
@endsection
