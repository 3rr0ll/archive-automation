<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Sign In - Archive Automation</title>

    <tallstackui:script />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-950 text-slate-100 flex items-center justify-center min-h-screen p-4 font-sans antialiased">

    <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl p-8 shadow-2xl space-y-6"
         x-data="loginForm">

        <!-- Header / Branding -->
        <div class="text-center space-y-2">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-indigo-600/10 border border-indigo-500/20 text-indigo-400 mb-2">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                </svg>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-white">Sign In</h1>
            <p class="text-sm text-slate-400">Enter your credentials to access Archive Automation</p>
        </div>

        <!-- Global Error Banner -->
        <div x-show="errorMessage" 
             x-cloak 
             class="p-4 bg-rose-500/10 border border-rose-500/30 rounded-xl text-rose-300 text-sm flex items-start gap-3">
            <svg class="w-5 h-5 text-rose-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
            <span x-text="errorMessage"></span>
        </div>

        <!-- Login Form -->
        <form @submit.prevent="submit" class="space-y-4">
            <!-- Email -->
            <div>
                <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
                    Email Address
                </label>
                <input id="email" 
                       type="email" 
                       x-model="email" 
                       required 
                       autofocus
                       autocomplete="email"
                       placeholder="name@example.com"
                       :class="fieldErrors.email ? 'border-rose-500 focus:border-rose-500' : 'border-slate-800 focus:border-indigo-500'"
                       class="w-full px-3.5 py-2.5 bg-slate-950 border rounded-xl text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 transition duration-150" />
                <span x-show="fieldErrors.email" 
                      x-cloak 
                      class="text-rose-400 text-xs mt-1.5 block" 
                      x-text="fieldErrors.email?.[0]"></span>
            </div>

            <!-- Password -->
            <div>
                <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
                    Password
                </label>
                <input id="password" 
                       type="password" 
                       x-model="password" 
                       required 
                       autocomplete="current-password"
                       placeholder="••••••••"
                       :class="fieldErrors.password ? 'border-rose-500 focus:border-rose-500' : 'border-slate-800 focus:border-indigo-500'"
                       class="w-full px-3.5 py-2.5 bg-slate-950 border rounded-xl text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 transition duration-150" />
                <span x-show="fieldErrors.password" 
                      x-cloak 
                      class="text-rose-400 text-xs mt-1.5 block" 
                      x-text="fieldErrors.password?.[0]"></span>
            </div>

            <!-- Submit Button -->
            <button type="submit" 
                    :disabled="isLoading" 
                    class="w-full mt-2 py-2.5 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-semibold rounded-xl transition duration-150 flex items-center justify-center gap-2 shadow-lg shadow-indigo-600/20">
                <span x-show="isLoading" 
                      x-cloak 
                      class="animate-spin inline-block w-4 h-4 border-2 border-current border-t-transparent rounded-full"></span>
                <span x-text="isLoading ? 'Signing In...' : 'Sign In'"></span>
            </button>
        </form>
    </div>

</body>
</html>
