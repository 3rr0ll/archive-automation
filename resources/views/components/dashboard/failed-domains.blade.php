<div>
    <h2 class="text-sm font-semibold text-zinc-900 mb-3">Failed Domains</h2>
    <div class="bg-white border border-zinc-200 rounded-lg p-4">
        <template x-if="failedDeployments.length === 0">
            <div class="text-center py-4">
                <div class="size-8 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto mb-2 border border-emerald-100">
                    <x-lucide-check class="size-4" />
                </div>
                <p class="text-xs font-semibold text-zinc-800 mb-1">No failed automations</p>
                <p class="text-[11px] text-zinc-500">All deployment tasks have succeeded without errors.</p>
            </div>
        </template>

        <template x-if="failedDeployments.length > 0">
            <div>
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-medium text-red-600" x-text="`${failedDeployments.length} failed deployment(s)`"></span>
                </div>
                <ul class="space-y-2.5 max-h-48 overflow-y-auto divide-y divide-zinc-100">
                    <template x-for="item in failedDeployments" :key="item.id">
                        <li class="pt-2 first:pt-0 flex items-center justify-between">
                            <div class="min-w-0 pr-2">
                                <p class="text-xs font-semibold text-zinc-900 truncate" x-text="item.domain"></p>
                                <p class="text-[10px] text-zinc-400" x-text="formatDate(item.created_at)"></p>
                            </div>
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-red-50 text-red-700 border border-red-200 shrink-0">
                                Failed
                            </span>
                        </li>
                    </template>
                </ul>
            </div>
        </template>
    </div>
</div>

