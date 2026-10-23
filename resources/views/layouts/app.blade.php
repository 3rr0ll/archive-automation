<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="bg-[#fafafa] dark:bg-[#121214]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Archive Automation' }}</title>

    <style>
        /* Immediately style html/body background to prevent white/dark flashes */
        html.dark, html.dark body {
            background-color: #121214 !important;
            color: #f4f4f5;
        }
        html:not(.dark), html:not(.dark) body {
            background-color: #fafafa !important;
            color: #18181b;
        }

        /* Pre-hydration curtain: keeps the screen clean and themed while Vite/CSS/Alpine initializes,
           preventing raw unstyled SVG/text flashes before skeleton loaders appear */
        body[data-page-loading="true"] {
            opacity: 0 !important;
            pointer-events: none !important;
        }
        body {
            transition: opacity 0.15s ease-out;
        }

        [x-cloak] {
            display: none !important;
        }
    </style>

    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || localStorage.getItem('ide_theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }

            // Mark body as loading immediately
            document.addEventListener('DOMContentLoaded', () => {
                // If Vite CSS is ready or on window load / Alpine init, reveal page smoothly
                const revealPage = () => {
                    document.body.removeAttribute('data-page-loading');
                };

                document.addEventListener('alpine:initialized', revealPage, { once: true });
                window.addEventListener('load', revealPage, { once: true });
                // Fallback guarantee in case events fired early
                setTimeout(revealPage, 250);
            });
        })();
    </script>

    @fonts

    <!-- Scripts and Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    @php
        $user = auth()->user();
        $authData = [
            'name' => $user?->name ?? 'Guest',
            'email' => $user?->email ?? 'guest@example.com',
            'role' => $user?->role ?? 'user',
            'isAdmin' => $user?->isAdmin() ?? false,
            'initial' => auth()->check() ? strtoupper(substr($user->name, 0, 1)) : 'G'
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
<body data-page-loading="true" class="bg-[#fafafa] dark:bg-[#121214] text-zinc-900 dark:text-zinc-100 min-h-screen antialiased flex flex-col font-sans selection:bg-zinc-950 selection:text-white dark:selection:bg-zinc-100 dark:selection:text-zinc-900 transition-colors duration-150">
    <x-global.confirmation-modal />
    <x-global.toast />

    @if(auth()->user()?->isAdmin())
        <!-- Application Sidebar (Admin only) -->
        <x-global.sidebar />
    @endif

    <!-- Main Shell -->
    <div class="flex-1 flex flex-col transition-all duration-200 {{ auth()->user()?->isAdmin() ? 'md:pl-64' : '' }}"
         @if(auth()->user()?->isAdmin())
             x-data
             :class="{ 'sidebar-collapsed-shell': $store.sidebar?.collapsed }"
         @endif>
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
