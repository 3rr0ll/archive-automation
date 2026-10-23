@extends('layouts.app')

@section('content')
<!-- IDE Window Shell -->
<div class="h-[calc(100vh-7.5rem)] flex flex-col border rounded-lg overflow-hidden transition-colors"
     :class="theme === 'dark' ? 'ide-dark bg-[#1e1e22] border-zinc-800 text-zinc-300' : 'bg-white border-zinc-200 text-zinc-700'"
     x-data="deploymentFilesPage({{ (int) $deployment->id }}, @js($deployment))">

    <!-- IDE Top Window Bar / Header -->
    <x-deployment-files.toolbar :deployment="$deployment" />

    <!-- IDE Body: Explorer Sidebar + Editor -->
    <div class="flex-1 min-h-0 flex overflow-hidden">
        <!-- Left: Activity / Sidebar (Explorer) -->
        <x-deployment-files.explorer />

        <!-- Right: Code Editor & Tab Area -->
        <x-deployment-files.editor />
    </div>

    <!-- IDE Bottom Status Bar -->
    <x-deployment-files.status-bar />
</div>
@endsection
