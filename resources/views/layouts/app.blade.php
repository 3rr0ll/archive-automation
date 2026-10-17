<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="bg-[#fafafa]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Archive Automation' }}</title>

    @fonts

    <!-- Scripts and Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    @php
        $authData = [
            'name' => auth()->user()?->name ?? 'Guest',
            'email' => auth()->user()?->email ?? 'guest@example.com',
            'initial' => auth()->check() ? strtoupper(substr(auth()->user()->name, 0, 1)) : 'G'
        ];
    @endphp
    
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('auth', {
                user: {!! json_encode($authData) !!}
            });
        });
    </script>
</head>
<body class="bg-[#fafafa] text-zinc-900 min-h-screen antialiased flex flex-col font-sans selection:bg-zinc-950 selection:text-white">
    <x-global.confirmation-modal />
    <x-global.toast />

    <!-- Application Sidebar -->
    <x-global.sidebar />

    <!-- Main Shell -->
    <div class="flex-1 flex flex-col md:pl-64">
        <!-- Top Navigation Header -->
        <x-global.navbar />

        <!-- Main Content Area -->
        <main class="flex-1 max-w-8xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
            {{ $slot ?? '' }}
            @yield('content')
        </main>
    </div>
</body>
</html>
