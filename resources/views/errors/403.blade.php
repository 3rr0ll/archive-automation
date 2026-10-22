<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-[#fafafa]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Forbidden | Archive Automation</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full flex flex-col items-center justify-center px-6 py-12 antialiased selection:bg-zinc-950 selection:text-white">
    <div class="w-full max-w-md text-center">
        <span class="text-xs font-semibold tracking-wider text-red-600 uppercase font-mono">403 Error</span>
        <h1 class="text-2xl font-bold tracking-tight text-zinc-900 mt-2 sm:text-3xl">Access Forbidden</h1>
        <p class="text-sm text-zinc-600 mt-3 leading-relaxed">
            You do not have the required administrative permissions to access this page.
        </p>

        <div class="mt-8 flex items-center justify-center gap-3">
            <a href="/dashboard" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-xs font-semibold text-white bg-zinc-900 hover:bg-zinc-800 transition shadow-xs cursor-pointer">
                <x-lucide-arrow-left class="size-4" />
                <span>Return to Dashboard</span>
            </a>
        </div>
    </div>
</body>
</html>
