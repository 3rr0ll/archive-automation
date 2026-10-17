@extends('layouts.app')

@section('content')
<div class="mx-auto py-6" x-data="usersPage(@js($users), {{ auth()->id() ?? 'null' }})">
    <!-- Skeleton Loading State -->
    <div x-show="isLoading">
        <x-skeleton.users-skeleton />
    </div>

    <!-- Main Content -->
    <div x-show="!isLoading" x-cloak>
        <x-users.header />

        <x-users.filters />

        <x-users.list />

        <x-users.drawer />
    </div>
</div>
@endsection
