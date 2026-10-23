<div>
    <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 mb-3">Failed Domains</h2>
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-lg p-4 overflow-hidden">
        <template x-if="failedDeployments.length === 0">
            <div class="text-center py-4">
                <div class="size-8 rounded-full bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mx-auto mb-2 border border-emerald-100 dark:border-emerald-800/60">
                    <x-lucide-check class="size-4" />
                </div>
                <p class="text-xs font-semibold text-zinc-800 dark:text-zinc-200 mb-1">No failed automations</p>
                <p class="text-[11px] text-zinc-500 dark:text-zinc-400">All deployment tasks have succeeded without errors.</p>
            </div>
        </template>

        <template x-if="failedDeployments.length > 0">
            <div>
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-medium text-red-600 dark:text-rose-300/90" x-text="`${failedDeployments.length} failed deployment(s)`"></span>
                </div>
                <ul class="space-y-2.5 max-h-48 overflow-y-auto overflow-x-hidden divide-y divide-zinc-100 dark:divide-zinc-800 pr-0.5">
                    <template x-for="item in failedDeployments" :key="item.id">
                        <li class="pt-2 first:pt-0 flex items-center justify-between hover:bg-zinc-50 dark:hover:bg-zinc-800/60 px-1.5 py-1 rounded transition cursor-pointer"
                            @click="window.location.href = '/deployments/' + (item.hash || item.id) + '/logs'">
                            <div class="min-w-0 pr-2">
                                <p class="text-xs font-semibold text-zinc-900 dark:text-zinc-100 truncate" x-text="item.domain"></p>
                                <p class="text-[10px] text-zinc-400 dark:text-zinc-500" x-text="formatDate(item.created_at)"></p>
                            </div>
                            <div class="flex items-center gap-1.5 shrink-0">
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-red-50 dark:bg-rose-950/40 text-red-700 dark:text-rose-300/90 border border-red-200 dark:border-rose-900/40">
                                    Failed
                                </span>
                                <button x-show="$store.auth.isAdmin"
                                        type="button"
                                        @click.stop="deleteDeploymentPrompt(item)"
                                        class="p-1 text-zinc-400 hover:text-red-600 dark:hover:text-rose-400 transition rounded"
                                        title="Delete deployment and RunCloud app">
                                    <x-lucide-trash-2 class="size-3.5" />
                                </button>
                            </div>
                        </li>
                    </template>
                </ul>
            </div>
        </template>
    </div>
</div>
