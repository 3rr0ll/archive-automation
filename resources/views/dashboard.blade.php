<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Archive Automation</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen p-8">
    <div class="max-w-4xl mx-auto bg-slate-800 p-6 rounded-xl border border-slate-700 flex justify-between items-center">
        <div>
            <h1 class="text-xl font-bold">Welcome, {{ auth()->user()->name }}</h1>
            <p class="text-slate-400 text-sm">You are logged into the Archive Automation Panel.</p>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-500 text-sm font-medium rounded-lg transition">
                Logout
            </button>
        </form>
    </div>
</body>
</html>