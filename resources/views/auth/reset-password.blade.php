<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Archive Automation</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-900 text-slate-100 flex items-center justify-center min-h-screen">
    <div class="w-full max-w-md bg-slate-800 p-8 rounded-xl shadow-lg border border-slate-700">
        <h2 class="text-2xl font-bold mb-6 text-center text-white">Set New Password</h2>

        <form x-data="{ 
            token: '{{ $token }}',
            email: '', 
            password: '', 
            password_confirmation: '',
            loading: false, 
            successMessage: '',
            errorMessage: '' 
        }" 
        @submit.prevent="
            loading = true; 
            successMessage = '';
            errorMessage = '';
            
            fetch('/api/reset-password', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ token, email, password, password_confirmation })
            })
            .then(async response => {
                loading = false;
                let data = await response.json();
                
                if (response.ok) {
                    successMessage = data.message || 'Password reset successfully! Redirecting...';
                    setTimeout(() => window.location.href = '/login', 2000);
                } else {
                    errorMessage = data.message || 'Failed to reset password.';
                }
            })
            .catch(error => {
                loading = false;
                errorMessage = 'An unexpected error occurred.';
            })
        " class="space-y-4">

            <!-- Success Banner -->
            <template x-if="successMessage">
                <div class="p-3 bg-green-500/20 border border-green-500 text-green-300 text-sm rounded" x-text="successMessage"></div>
            </template>

            <!-- Error Banner -->
            <template x-if="errorMessage">
                <div class="p-3 bg-red-500/20 border border-red-500 text-red-300 text-sm rounded" x-text="errorMessage"></div>
            </template>

            <input type="hidden" x-model="token">

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1">Email Address</label>
                <input type="email" x-model="email" required autofocus
                    class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white focus:outline-none focus:border-indigo-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1">New Password</label>
                <input type="password" x-model="password" required
                    class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white focus:outline-none focus:border-indigo-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1">Confirm New Password</label>
                <input type="password" x-model="password_confirmation" required
                    class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white focus:outline-none focus:border-indigo-500">
            </div>

            <button type="submit" :disabled="loading" 
                class="w-full py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold rounded-lg transition duration-200 flex items-center justify-center">
                <span x-show="!loading">Reset Password</span>
                <span x-show="loading">Saving...</span>
            </button>
        </form>
    </div>
</body>
</html>