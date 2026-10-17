@extends('layouts.app')

@section('content')
<div class="mx-auto py-6" x-data="serversPage(@js($servers))">
    <!-- Skeleton Loading State -->
    <div x-show="isLoading">
        <x-skeleton.servers-skeleton />
    </div>

    <!-- Main Content -->
    <div x-show="!isLoading" x-cloak>
        <x-servers.header />

        <x-servers.filters />

        <x-servers.list />
    </div>
</div>
@endsection
