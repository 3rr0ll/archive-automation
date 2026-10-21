@extends('layouts.app')

@section('content')
<div class="mx-auto py-6 space-y-6" x-data="googleSearchConsolePage()" x-init="init()">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 tracking-tight">Google Search Console</h1>
            <p class="mt-1 text-sm text-zinc-500">Manage the service account used for domain verification and sitemap submission.</p>
        </div>
        <div class="flex items-center gap-2">
            @if (filled(config('services.google_search_console.client_id')))
                <a href="{{ route('google.redirect') }}"
                   class="inline-flex items-center justify-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                    <x-lucide-log-in class="size-4" /> Connect with Google (OAuth)
                </a>
            @endif
            <button type="button" @click="openCreateForm()"
                    class="inline-flex items-center justify-center gap-2 rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800">
                <x-lucide-plus class="size-4" /> Add service account
            </button>
        </div>
    </div>

    @if (session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
            {{ session('error') }}
        </div>
    @endif

    <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
        <p class="font-medium">Automatic deployment workflow</p>
        <p class="mt-1 text-blue-800">The active account (OAuth or Service Account) requests a verification token, creates the Cloudflare DNS TXT record, verifies the domain, adds the optional human owner, submits the sitemap, and can publish indexing requests via Google Indexing API.</p>
    </div>

    <section x-show="showAccountForm" x-cloak class="rounded-lg border border-zinc-200 bg-white p-5">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-zinc-900" x-text="editingId ? 'Edit service account' : 'Add service account'"></h2>
                <p class="mt-1 text-xs text-zinc-500">The JSON key is encrypted before it is stored and is never returned by the API.</p>
            </div>
            <button type="button" @click="closeAccountForm()" class="text-zinc-400 hover:text-zinc-700"><x-lucide-x class="size-5" /></button>
        </div>

        <form @submit.prevent="saveAccount()" class="mt-5 space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="gsc-name" class="block text-xs font-medium text-zinc-700 mb-1.5">Display name</label>
                    <input id="gsc-name" type="text" x-model="accountForm.name" required placeholder="Production Search Console"
                           class="block w-full rounded-md border border-zinc-200 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none">
                </div>
                <div>
                    <label for="gsc-owner" class="block text-xs font-medium text-zinc-700 mb-1.5">Human owner email <span class="text-zinc-400">(optional)</span></label>
                    <input id="gsc-owner" type="email" x-model="accountForm.owner_email" placeholder="owner@gmail.com"
                           class="block w-full rounded-md border border-zinc-200 px-3 py-2 text-sm focus:border-zinc-400 focus:outline-none">
                </div>
            </div>
            <div>
                <label for="gsc-json" class="block text-xs font-medium text-zinc-700 mb-1.5">
                    Service-account JSON <span x-show="editingId" class="text-zinc-400">(leave blank to keep the current key)</span>
                </label>
                <textarea id="gsc-json" x-model="accountForm.credentials_json" :required="!editingId" rows="9"
                          autocomplete="off" spellcheck="false" placeholder='{"type":"service_account", ...}'
                          class="block w-full rounded-md border border-zinc-200 px-3 py-2 font-mono text-xs focus:border-zinc-400 focus:outline-none"></textarea>
            </div>
            <label class="inline-flex items-center gap-2 text-sm text-zinc-700">
                <input type="checkbox" x-model="accountForm.is_active" class="rounded border-zinc-300 text-zinc-900 focus:ring-zinc-500">
                Make this the active deployment account
            </label>
            <div class="flex justify-end gap-2">
                <button type="button" @click="closeAccountForm()" class="rounded-md border border-zinc-200 px-4 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50">Cancel</button>
                <button type="submit" :disabled="isSaving" class="rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-50"
                        x-text="isSaving ? 'Saving...' : 'Save account'"></button>
            </div>
        </form>
    </section>

    <div x-show="isLoading" class="rounded-lg border border-zinc-200 bg-white p-10 text-center text-sm text-zinc-500">Loading Google service accounts...</div>

    <section x-show="!isLoading" x-cloak class="space-y-3">
        <div x-show="legacyFallbackConfigured" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Legacy environment credentials are configured and will be used only when no database account is active.
        </div>

        <div x-show="accounts.length === 0" class="rounded-lg border border-dashed border-zinc-300 bg-white p-10 text-center">
            <p class="text-sm font-medium text-zinc-800">No Google service accounts added</p>
            <p class="mt-1 text-xs text-zinc-500">Add the JSON key downloaded from Google Cloud.</p>
        </div>

        <template x-for="account in accounts" :key="account.id">
            <article class="rounded-lg border bg-white p-5" :class="account.is_active ? 'border-emerald-300 ring-1 ring-emerald-100' : 'border-zinc-200'">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="font-semibold text-zinc-900" x-text="account.name"></h2>
                            <span x-show="account.is_active" class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">Active</span>
                            <span x-show="!account.is_active" class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-600">Inactive</span>
                        </div>
                        <p class="mt-1 truncate font-mono text-xs text-zinc-600" x-text="account.client_email"></p>
                        <p x-show="account.owner_email" class="mt-1 text-xs text-zinc-500">Human owner: <span x-text="account.owner_email"></span></p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" @click="testAccount(account)" :disabled="testingId === account.id"
                                class="rounded-md border border-zinc-200 px-3 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50 disabled:opacity-50"
                                x-text="testingId === account.id ? 'Testing...' : 'Test'"></button>
                        <button x-show="!account.is_active" type="button" @click="activateAccount(account)" :disabled="activatingId === account.id"
                                class="rounded-md border border-emerald-600 bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
                                x-text="activatingId === account.id ? 'Activating...' : 'Activate'"></button>
                        <button type="button" @click="openEditForm(account)" class="rounded-md border border-zinc-200 px-3 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50">Edit</button>
                        <button type="button" @click="deleteAccount(account)" class="rounded-md border border-red-200 px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50">Delete</button>
                    </div>
                </div>
            </article>
        </template>
    </section>

    <section class="rounded-lg border border-zinc-200 bg-white p-5">
        <h2 class="font-semibold text-zinc-900">Register an existing domain</h2>
        <p class="mt-1 text-sm text-zinc-500">Verify it through Cloudflare, add it to Search Console, and submit its sitemap.</p>
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
            <button type="submit" :disabled="!hasActiveAccount || isRegistering"
                    class="rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-50"
                    x-text="isRegistering ? 'Registering...' : 'Verify & submit sitemap'"></button>
        </form>
        <p x-show="!hasActiveAccount" class="mt-3 text-xs text-amber-700">Activate a service account before registering a domain.</p>
        <div x-show="registrationResult" x-cloak class="mt-4 rounded-md px-3 py-2 text-sm"
             :class="registrationResult?.success ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-700'"
             x-text="registrationResult?.message"></div>
    </section>
</div>
@endsection
