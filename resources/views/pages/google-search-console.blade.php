@extends('layouts.app')

@section('content')
<div class="mx-auto py-6 space-y-6" x-data="googleSearchConsolePage()" x-init="init()">
    <div>
        <h1 class="text-2xl font-bold text-zinc-900 tracking-tight">Google Search Console</h1>
        <p class="mt-1 text-sm text-zinc-500">Connect one Google account and use it automatically after every deployment.</p>
    </div>

    @if(session('google_success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('google_success') }}
        </div>
    @endif
    @if(session('google_error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ session('google_error') }}
        </div>
    @endif

    <div x-show="isLoading" class="rounded-lg border border-zinc-200 bg-white p-10 text-center text-sm text-zinc-500">
        Loading Google settings…
    </div>

    <section x-show="!isLoading" x-cloak class="rounded-lg border border-zinc-200 bg-white p-5">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-5">
            <div class="flex items-start gap-3 min-w-0">
                <div class="size-10 rounded-lg flex items-center justify-center shrink-0"
                     :class="settings.connected ? 'bg-emerald-100 text-emerald-700' : 'bg-zinc-100 text-zinc-500'">
                    <x-lucide-circle-check x-show="settings.connected" class="size-5" />
                    <x-lucide-unplug x-show="!settings.connected" class="size-5" />
                </div>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-base font-semibold text-zinc-900" x-text="settings.connected ? 'Google account connected' : 'Google account not connected'"></h2>
                        <span x-show="settings.active" class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">Active</span>
                        <span x-show="settings.connected && !settings.active" class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-600">Inactive</span>
                    </div>
                    <p x-show="settings.connected" class="mt-1 text-sm text-zinc-600 truncate" x-text="settings.account_email"></p>
                    <p x-show="!settings.client_configured" class="mt-2 text-xs text-amber-700">Set GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, and GOOGLE_REDIRECT_URI before connecting.</p>
                    <p x-show="settings.client_configured && !settings.connected" class="mt-2 text-xs text-zinc-500">OAuth application settings are configured. Connect the Google account that should own and manage deployed domains.</p>
                </div>
            </div>

            <div class="flex flex-wrap gap-2 shrink-0">
                <a x-show="!settings.connected" :class="!settings.client_configured ? 'pointer-events-none opacity-50' : ''"
                   href="{{ route('google-search-console.authorize') }}"
                   class="inline-flex items-center gap-2 rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800">
                    <x-lucide-log-in class="size-4" /> Connect Google
                </a>
                <button x-show="settings.connected" type="button" @click="testConnection()" :disabled="isTesting"
                        class="inline-flex items-center gap-2 rounded-md border border-zinc-200 px-3 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50 disabled:opacity-50">
                    <span x-show="isTesting" class="size-3.5 animate-spin rounded-full border-2 border-zinc-500 border-t-transparent"></span>
                    <span x-text="isTesting ? 'Testing…' : 'Test connection'"></span>
                </button>
                <button x-show="settings.connected" type="button" @click="toggleActive()" :disabled="isUpdating"
                        class="rounded-md px-3 py-2 text-sm font-medium border disabled:opacity-50"
                        :class="settings.active ? 'border-zinc-200 text-zinc-700 hover:bg-zinc-50' : 'border-emerald-600 bg-emerald-600 text-white hover:bg-emerald-700'"
                        x-text="settings.active ? 'Deactivate' : 'Activate'"></button>
                <button x-show="settings.connected" type="button" @click="disconnect()"
                        class="rounded-md border border-red-200 px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50">Disconnect</button>
            </div>
        </div>

        <dl class="mt-5 grid gap-3 border-t border-zinc-100 pt-4 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-xs text-zinc-500">Configured redirect URI</dt>
                <dd class="mt-1 break-all font-mono text-xs text-zinc-700" x-text="settings.redirect_uri || 'Not configured'"></dd>
            </div>
            <div>
                <dt class="text-xs text-zinc-500">Credential storage</dt>
                <dd class="mt-1 text-xs text-zinc-700">Client credentials in environment; OAuth refresh token encrypted in the database.</dd>
            </div>
        </dl>
    </section>

    <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
        <div class="flex gap-3">
            <x-lucide-info class="size-5 shrink-0 mt-0.5" />
            <div>
                <p class="font-medium">Automatic deployment workflow</p>
                <p class="mt-1 text-blue-800">When the connection is active, deployments request the domain verification token, add it through Cloudflare DNS, verify the property, and submit the site sitemap.</p>
                <p class="mt-2 text-blue-800">For a single homepage request, use <a href="https://search.google.com/search-console/inspect" target="_blank" rel="noopener noreferrer" class="font-medium underline">Google URL Inspection</a>. Ordinary pages cannot use Google’s specialized Indexing API.</p>
            </div>
        </div>
    </div>

    <section class="rounded-lg border border-zinc-200 bg-white p-5">
        <div class="flex items-start gap-3">
            <div class="size-9 rounded-md bg-zinc-100 flex items-center justify-center shrink-0">
                <x-lucide-globe-2 class="size-4.5 text-zinc-600" />
            </div>
            <div>
                <h2 class="text-base font-semibold text-zinc-900">Register a domain now</h2>
                <p class="mt-1 text-sm text-zinc-500">Verify the domain through Cloudflare and submit its sitemap using the connected Google account.</p>
            </div>
        </div>

        <form @submit.prevent="registerDomain()" class="mt-5 grid gap-4 lg:grid-cols-[1fr_1fr_auto] lg:items-end">
            <div>
                <label for="gsc-domain" class="block text-xs font-medium text-zinc-700 mb-1.5">Domain</label>
                <input id="gsc-domain" type="text" x-model="domainForm.domain" required placeholder="example.com"
                       class="block w-full rounded-md border border-zinc-200 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none">
            </div>
            <div>
                <label for="gsc-sitemap" class="block text-xs font-medium text-zinc-700 mb-1.5">Sitemap URL <span class="text-zinc-400">(optional)</span></label>
                <input id="gsc-sitemap" type="url" x-model="domainForm.sitemap_url" placeholder="https://example.com/sitemap.xml"
                       class="block w-full rounded-md border border-zinc-200 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none">
            </div>
            <button type="submit" :disabled="!settings.connected || !settings.active || isRegistering"
                    class="inline-flex items-center justify-center gap-2 rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-50 disabled:cursor-not-allowed">
                <span x-show="isRegistering" class="size-3.5 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
                <span x-text="isRegistering ? 'Registering…' : 'Verify & submit sitemap'"></span>
            </button>
        </form>
        <p x-show="!settings.connected || !settings.active" class="mt-3 text-xs text-amber-700">Connect and activate Google Search Console before registering a domain.</p>
        <div x-show="registrationResult" x-cloak class="mt-4 rounded-md px-3 py-2 text-sm"
             :class="registrationResult?.success ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-700'"
             x-text="registrationResult?.message"></div>
    </section>
</div>
@endsection
