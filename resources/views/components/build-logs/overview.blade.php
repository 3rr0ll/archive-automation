<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
    <!-- 1. Status -->
    <div class="bg-white border border-zinc-200 rounded-lg p-3.5 flex items-start gap-3">
        <div class="size-8 rounded-lg flex items-center justify-center shrink-0 border"
             :class="{
                 'bg-zinc-100 text-zinc-900 border-zinc-200': deployment?.status === 'completed' || deployment?.status === 'pending' || deployment?.status === 'processing',
                 'bg-red-50 text-red-600 border-red-100': deployment?.status === 'failed'
             }">
            <template x-if="deployment?.status === 'completed'">
                <x-lucide-check-circle-2 class="size-4.5 text-zinc-900" />
            </template>
            <template x-if="deployment?.status === 'pending' || deployment?.status === 'processing'">
                <span class="animate-spin inline-block size-4 border-2 border-zinc-300 border-t-zinc-900 rounded-full"></span>
            </template>
            <template x-if="deployment?.status === 'failed'">
                <x-lucide-alert-circle class="size-4.5" />
            </template>
        </div>
        <div class="min-w-0 flex-1">
            <div class="text-[10px] font-semibold text-zinc-400 uppercase tracking-wider">Status</div>
            <div class="text-xs font-semibold text-zinc-900 mt-0.5 truncate capitalize flex items-center gap-1.5">
                <span x-text="deployment ? (deployment.status === 'completed' ? 'Deploy Succeeded' : formatLabel(deployment.status)) : 'Loading...'"></span>
            </div>
        </div>
    </div>

    <!-- 2. Duration -->
    <div class="bg-white border border-zinc-200 rounded-lg p-3.5 flex items-start gap-3">
        <div class="size-8 rounded-lg bg-zinc-50 text-zinc-500 border border-zinc-100 flex items-center justify-center shrink-0">
            <x-lucide-timer class="size-4.5" />
        </div>
        <div class="min-w-0 flex-1">
            <div class="text-[10px] font-semibold text-zinc-400 uppercase tracking-wider">Duration</div>
            <div class="text-xs font-semibold text-zinc-900 mt-0.5 truncate" x-text="duration"></div>
        </div>
    </div>

    <!-- 3. Deployed Timestamp -->
    <div class="bg-white border border-zinc-200 rounded-lg p-3.5 flex items-start gap-3">
        <div class="size-8 rounded-lg bg-zinc-50 text-zinc-500 border border-zinc-100 flex items-center justify-center shrink-0">
            <x-lucide-calendar class="size-4.5" />
        </div>
        <div class="min-w-0 flex-1">
            <div class="text-[10px] font-semibold text-zinc-400 uppercase tracking-wider">Deployed</div>
            <div class="text-xs font-semibold text-zinc-900 mt-0.5 truncate" x-text="formatDateTime(deployment?.created_at)"></div>
        </div>
    </div>

    <!-- 4. Trigger -->
    <div class="bg-white border border-zinc-200 rounded-lg p-3.5 flex items-start gap-3">
        <div class="size-8 rounded-lg bg-zinc-50 text-zinc-500 border border-zinc-100 flex items-center justify-center shrink-0">
            <x-lucide-git-branch class="size-4.5" />
        </div>
        <div class="min-w-0 flex-1">
            <div class="text-[10px] font-semibold text-zinc-400 uppercase tracking-wider">Trigger</div>
            <div class="text-xs font-semibold text-zinc-900 mt-0.5 truncate" x-text="triggerText"></div>
        </div>
    </div>

    <!-- 5. Server / Target -->
    <div class="bg-white border border-zinc-200 rounded-lg p-3.5 flex items-start gap-3">
        <div class="size-8 rounded-lg bg-zinc-50 text-zinc-500 border border-zinc-100 flex items-center justify-center shrink-0">
            <x-lucide-server class="size-4.5" />
        </div>
        <div class="min-w-0 flex-1">
            <div class="text-[10px] font-semibold text-zinc-400 uppercase tracking-wider">Server</div>
            <div class="text-xs font-semibold text-zinc-900 mt-0.5 truncate" x-text="deployment?.server ? deployment.server.name : (deployment?.server_id ? 'Server #' + deployment.server_id : '—')"></div>
        </div>
    </div>
</div>
