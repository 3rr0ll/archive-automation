@extends('layouts.app')

@section('content')
<div class="mx-auto py-6" x-data="usersPage(@js($users), {{ auth()->id() ?? 'null' }})">
    <x-users.header />

    <x-users.filters />

    <x-users.list />

    <x-users.drawer />
</div>
@endsection
