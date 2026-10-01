<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Archive Automation</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-900 text-slate-100 flex items-center justify-center min-h-screen">
    <div class="w-full max-w-md bg-slate-800 p-8 rounded-xl shadow-lg border border-slate-700">
        <h2 class="text-2xl font-bold mb-6 text-center text-white">Archive Automation</h2>

        @if ($errors->any())
            <div class="mb-4 p-3 bg-red-500/20 border border-red-500 text-red-300 text-sm rounded">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1">Email Address</label>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus
                    class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white focus:outline-none focus:border-indigo-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1">Password</label>
                <input type="password" name="password" required
                    class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white focus:outline-none focus:border-indigo-500">
            </div>

            <div class="flex items-center justify-between text-sm mt-2">
                <label class="flex items-center text-slate-400">
                    <input type="checkbox" name="remember" class="rounded bg-slate-900 border-slate-700 text-indigo-600 focus:ring-indigo-500">
                    <span class="ml-2">Remember me</span>
                </label>
                <a href="#" class="text-indigo-400 hover:underline">Forgot password?</a>
            </div>

            <button type="submit" class="w-full py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold rounded-lg transition duration-200">
                Sign In
            </button>
        </form>
    </div>
</body>
</html>