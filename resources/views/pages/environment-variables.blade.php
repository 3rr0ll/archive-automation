@extends('layouts.app')

@section('content')
<div class="mx-auto py-6" x-data="environmentVariablesPage(@js($variables))">
    <!-- Skeleton Loading State -->
    <div x-show="isLoading">
        <x-skeleton.environment-variables-skeleton />
    </div>

    <!-- Main Content -->
    <div x-show="!isLoading" x-cloak>
        <x-environment-variables.header />

        <x-environment-variables.drawer />

        <x-environment-variables.filters />

        <x-environment-variables.list />
    </div>
</div>
@endsection
