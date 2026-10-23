<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Google account connection</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-zinc-50 text-zinc-900 flex items-center justify-center p-6 dark:bg-zinc-950 dark:text-zinc-100">
    <main class="w-full max-w-md rounded-xl border border-zinc-200 bg-white p-6 text-center shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
        <h1 class="text-lg font-semibold">
            {{ $success ? 'Google account connected' : 'Google connection failed' }}
        </h1>
        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ $message }}</p>
        <p class="mt-4 text-xs text-zinc-500">This tab should close automatically.</p>
        <a href="{{ route('google-search-console') }}"
           class="mt-5 inline-flex rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800 dark:border dark:border-zinc-700">
            Return to Google Search Console
        </a>
    </main>

    <script>
        (() => {
            const result = {
                type: 'google-oauth-complete',
                success: @js($success),
                message: @js($message),
                account: @js($account ?? null),
            };

            // 1. Post to opener if available
            if (window.opener && !window.opener.closed) {
                try {
                    window.opener.postMessage(result, window.location.origin);
                } catch (e) {}
            }

            // 2. BroadcastChannel for cross-tab/popup communication
            try {
                const channel = new BroadcastChannel('google_oauth_channel');
                channel.postMessage(result);
                channel.close();
            } catch (e) {}

            // 3. Fallback localStorage trigger
            try {
                localStorage.setItem('google_oauth_result', JSON.stringify({
                    timestamp: Date.now(),
                    ...result
                }));
            } catch (e) {}

            // Close the popup window automatically
            window.setTimeout(() => {
                try {
                    window.close();
                } catch (e) {}
            }, 600);
        })();
    </script>
</body>
</html>
