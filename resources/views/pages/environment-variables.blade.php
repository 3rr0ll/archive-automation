@extends('layouts.app')

@section('content')
<div class="mx-auto py-6" x-data="environmentVariablesPage(@js($variables))">
    <x-environment-variables.header />

    <x-environment-variables.drawer />

    <x-environment-variables.filters />

    <x-environment-variables.list />
</div>
@endsection
