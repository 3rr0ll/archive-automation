<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Archive Automation</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-900 text-slate-100 flex items-center justify-center min-h-screen">
    <div class="w-full max-w-md bg-slate-800 p-8 rounded-xl shadow-lg border border-slate-700">
        <h2 class="text-2xl font-bold mb-4 text-center text-white">Reset Password</h2>
        <p class="text-slate-400 text-sm mb-6 text-center">Enter your email and we'll send you a password reset link.</p>

        <form x-data="{ 
            email: '', 
            loading: false, 
            successMessage: '', 
            errorMessage: '' 
        }" 
        @submit.prevent="
            loading = true; 
            successMessage = '';
            errorMessage = '';
            
            fetch('/api/forgot-password', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ email })
            })
            .then(async response => {
                loading = false;
                let data = await response.json();
                
                if (response.ok) {
                    successMessage = data.message || 'Reset link sent successfully!';
                } else {
                    errorMessage = data.message || (data.errors && data.errors.email ? data.errors.email[0] : 'Failed to send reset link.');
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

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1">Email Address</label>
                <input type="email" x-model="email" required autofocus
                    class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white focus:outline-none focus:border-indigo-500">
            </div>

            <button type="submit" :disabled="loading" 
                class="w-full py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold rounded-lg transition duration-200 flex items-center justify-center">
                <span x-show="!loading">Send Reset Link</span>
                <span x-show="loading">Sending...</span>
            </button>

            <div class="text-center mt-4">
                <a href="/login" class="text-sm text-indigo-400 hover:underline">Back to Login</a>
            </div>
        </form>
    </div>
</body>
</html>