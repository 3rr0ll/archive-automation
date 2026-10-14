@extends('layouts.app')

@section('content')
<div class="mx-auto py-6" x-data="serversPage(@js($servers))">
    <x-servers.header />

    <x-servers.filters />

    <x-servers.list />
</div>
@endsection
