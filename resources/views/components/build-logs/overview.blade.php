<div class="py-2">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-y-6 gap-x-8">
        <!-- 1. STATUS -->
        <div class="flex items-start gap-3.5">
            <div class="size-8 rounded flex items-center justify-center shrink-0"
                 :class="{
                     'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400': deployment?.status === 'completed',
                     'bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200': deployment?.status === 'pending' || deployment?.status === 'processing',
                     'bg-red-50 dark:bg-red-950/60 text-red-600 dark:text-red-400': deployment?.status === 'failed'
                 }">
                <template x-if="deployment?.status === 'completed'">
                    <x-lucide-check-circle-2 class="size-4.5" />
                </template>
                <template x-if="deployment?.status === 'pending' || deployment?.status === 'processing'">
                    <span class="animate-spin inline-block size-4 border-2 border-zinc-300 dark:border-zinc-600 border-t-zinc-800 dark:border-t-zinc-200 rounded-full"></span>
                </template>
                <template x-if="deployment?.status === 'failed'">
                    <x-lucide-alert-circle class="size-4.5" />
                </template>
            </div>
            <div class="min-w-0">
                <div class="text-[11px] font-semibold text-zinc-400 dark:text-zinc-500 uppercase tracking-wider">STATUS</div>
                <div class="text-xs font-semibold text-zinc-900 dark:text-zinc-100 mt-1 flex items-center gap-1.5 flex-wrap">
                    <span x-text="deployment ? (deployment.status === 'completed' ? 'Deploy succeeded' : formatLabel(deployment.status)) : 'Loading...'"></span>
                    <template x-if="deployment?.status === 'completed'">
                        <span class="text-zinc-300 dark:text-zinc-600 font-normal">|</span>
                    </template>
                    <template x-if="deployment?.status === 'completed'">
                        <span class="text-zinc-500 dark:text-zinc-400 font-medium">Live</span>
                    </template>
                </div>
            </div>
        </div>

        <!-- 2. DURATION -->
        <div class="flex items-start gap-3.5">
            <div class="size-8 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 flex items-center justify-center shrink-0">
                <x-lucide-timer class="size-4" />
            </div>
            <div class="min-w-0">
                <div class="text-[11px] font-semibold text-zinc-400 dark:text-zinc-500 uppercase tracking-wider">DURATION</div>
                <div class="text-xs font-semibold text-zinc-900 dark:text-zinc-100 mt-1" x-text="duration"></div>
            </div>
        </div>

        <!-- 3. DEPLOYED -->
        <div class="flex items-start gap-3.5">
            <div class="size-8 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 flex items-center justify-center shrink-0">
                <x-lucide-calendar class="size-4" />
            </div>
            <div class="min-w-0">
                <div class="text-[11px] font-semibold text-zinc-400 dark:text-zinc-500 uppercase tracking-wider">DEPLOYED</div>
                <div class="text-xs font-medium text-zinc-800 dark:text-zinc-200 mt-1" x-text="formatDateTime(deployment?.created_at)"></div>
            </div>
        </div>

        <!-- 4. TRIGGER -->
        <div class="flex items-start gap-3.5">
            <div class="size-8 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 flex items-center justify-center shrink-0">
                <x-lucide-upload-cloud class="size-4" />
            </div>
            <div class="min-w-0">
                <div class="text-[11px] font-semibold text-zinc-400 dark:text-zinc-500 uppercase tracking-wider">TRIGGER</div>
                <div class="text-xs font-normal text-zinc-700 dark:text-zinc-300 mt-1 truncate" x-text="triggerText"></div>
            </div>
        </div>

        <!-- 5. SOURCE -->
        <div class="flex items-start gap-3.5">
            <div class="size-8 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 flex items-center justify-center shrink-0">
                <x-lucide-git-commit class="size-4" />
            </div>
            <div class="min-w-0">
                <div class="text-[11px] font-semibold text-zinc-400 dark:text-zinc-500 uppercase tracking-wider">SOURCE</div>
                <div class="text-xs font-medium text-zinc-800 dark:text-zinc-200 mt-1 truncate" x-text="sourceText"></div>
            </div>
        </div>

        <!-- 6. SERVER -->
        <div class="flex items-start gap-3.5">
            <div class="size-8 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 flex items-center justify-center shrink-0">
                <x-lucide-server class="size-4" />
            </div>
            <div class="min-w-0">
                <div class="text-[11px] font-semibold text-zinc-400 dark:text-zinc-500 uppercase tracking-wider">SERVER</div>
                <div class="text-xs font-medium text-zinc-800 dark:text-zinc-200 mt-1 truncate" x-text="deployment?.server ? deployment.server.name : (deployment?.server_id ? 'Server #' + deployment.server_id : '—')"></div>
            </div>
        </div>
    </div>
</div>
