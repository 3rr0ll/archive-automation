<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="bg-white" style="background-color: #ffffff;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Sign In</title>

    @fonts

    <tallstackui:script />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>


<body class="bg-white text-zinc-900 flex items-center justify-center min-h-screen p-4 font-sans antialiased selection:bg-zinc-950 selection:text-white" style="background-color: #ffffff;">
    <x-auth.login-card />
</body>
</html>

