<template x-if="missingDomains.length > 0">
    <div class="bg-white dark:bg-[#18181b] border border-zinc-200 dark:border-zinc-800 rounded-lg p-5 transition-colors">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-xs font-semibold text-zinc-900 dark:text-zinc-100 uppercase tracking-wider flex items-center gap-2">
                <x-lucide-alert-circle class="size-4 text-zinc-400 dark:text-zinc-500" />
                <span>Domains Not Found</span>
                <span class="text-xs font-normal normal-case text-zinc-500 dark:text-zinc-400" x-text="`(${missingDomains.length})`"></span>
            </h3>
        </div>
        <p class="text-xs text-zinc-500 dark:text-zinc-400 mb-3">
            The following domains do not exist in the deployments database:
        </p>
        <div class="flex flex-wrap gap-2">
            <template x-for="(dom, idx) in missingDomains" :key="idx">
                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-mono bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700"
                      x-text="dom"></span>
            </template>
        </div>
    </div>
</template>
