<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - Archive Automation</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-900 text-slate-100 flex items-center justify-center min-h-screen">
    <div class="w-full max-w-md bg-slate-800 p-8 rounded-xl shadow-lg border border-slate-700">
        <h2 class="text-2xl font-bold mb-6 text-center text-white">Sign In</h2>

        <form x-data="{ 
            email: '', 
            password: '', 
            device_name: 'web-browser',
            loading: false, 
            errorMessage: '' 
        }" 
        @submit.prevent="
            loading = true; 
            errorMessage = '';
            
            fetch('/api/login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ email, password, device_name })
            })
            .then(async response => {
                loading = false;
                let data = await response.json();
                
                if (response.ok) {
                    localStorage.setItem('auth_token', data.access_token);
                    window.location.href = '/dashboard';
                } else {
                    errorMessage = data.message || (data.errors && data.errors.email ? data.errors.email[0] : 'Invalid credentials.');
                }
            })
            .catch(error => {
                loading = false;
                errorMessage = 'An unexpected error occurred.';
            })
        " class="space-y-4">

            <!-- Error Banner -->
            <template x-if="errorMessage">
                <div class="p-3 bg-red-500/20 border border-red-500 text-red-300 text-sm rounded" x-text="errorMessage"></div>
            </template>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1">Email Address</label>
                <input type="email" x-model="email" required autofocus
                    class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white focus:outline-none focus:border-indigo-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1">Password</label>
                <input type="password" x-model="password" required
                    class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white focus:outline-none focus:border-indigo-500">
            </div>

            <div class="flex items-center justify-between text-sm">
                <a href="/forgot-password" class="text-indigo-400 hover:underline">Forgot password?</a>
            </div>

            <button type="submit" :disabled="loading" 
                class="w-full py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold rounded-lg transition duration-200 flex items-center justify-center">
                <span x-show="!loading">Sign In</span>
                <span x-show="loading">Logging in...</span>
            </button>
        </form>
    </div>
</body>
</html>