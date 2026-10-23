<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
    <div>
        <h1 class="text-2xl font-bold text-zinc-900 tracking-tight">Environment Variables</h1>
        <p class="mt-1 text-sm text-zinc-500">
            Store API keys, tokens, and config securely.
        </p>
    </div>
    <div class="flex items-center gap-3">
        <button type="button" 
                @click="openModal()" 
                class="inline-flex items-center justify-center gap-2 px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-white bg-zinc-900 hover:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-zinc-900 transition-colors cursor-pointer">
            <x-lucide-plus class="size-4" />
            <span>Add Environment Variable</span>
        </button>
    </div>
</div>
