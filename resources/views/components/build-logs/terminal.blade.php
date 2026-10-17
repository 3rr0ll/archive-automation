<div class="bg-white border border-zinc-200 rounded-lg overflow-hidden">
    <!-- Logs Toolbar -->
    <x-build-logs.toolbar />

    <!-- Terminal Log Feed Body (Light Zinc Theme) -->
    <div id="log-terminal-output" class="bg-white text-zinc-800 font-mono text-[12px] leading-relaxed p-4 min-h-[420px] max-h-[640px] overflow-y-auto selection:bg-zinc-200">
        <!-- Loading Indicator -->
        <template x-if="isLoading">
            <div class="py-16 flex flex-col items-center justify-center text-zinc-400">
                <span class="animate-spin inline-block size-5 border-2 border-zinc-300 border-t-zinc-800 rounded-full mb-3"></span>
                <span class="text-xs font-sans text-zinc-500 font-medium">Streaming build logs...</span>
            </div>
        </template>

        <!-- Empty Logs State -->
        <template x-if="!isLoading && filteredLogs.length === 0">
            <div class="py-20 text-center text-zinc-500 font-sans">
                <div class="size-10 rounded-full bg-zinc-100 flex items-center justify-center mx-auto mb-3 text-zinc-400">
                    <x-lucide-terminal class="size-5" />
                </div>
                <div class="text-sm font-semibold text-zinc-800">No logs to show</div>
                <p class="text-xs text-zinc-500 mt-1 max-w-sm mx-auto" x-text="searchQuery ? 'No log entries match your filter query.' : 'Waiting for deployment tasks to dispatch logs.'"></p>
            </div>
        </template>

        <!-- Logs Feed -->
        <template x-if="!isLoading && filteredLogs.length > 0">
            <div class="space-y-1">
                <template x-for="(log, idx) in filteredLogs" :key="log.id || idx">
                    <div class="flex items-start gap-3 py-1.5 px-2 rounded-md hover:bg-zinc-50 transition border border-transparent hover:border-zinc-100 group">
                        <!-- Timestamp -->
                        <span class="text-zinc-400 text-[11px] shrink-0 select-none pt-0.5" x-text="formatTime(log.created_at)"></span>

                        <!-- Step Name Badge (Monochrome Theme & Normalized Text) -->
                        <span class="text-[10px] px-1.5 py-0.5 rounded font-medium shrink-0 uppercase tracking-wider select-none"
                              :class="{
                                  'bg-zinc-900 text-white': log.status === 'success',
                                  'bg-zinc-200 text-zinc-900 border border-zinc-300 font-semibold': log.status === 'in_progress',
                                  'bg-red-50 text-red-700 border border-red-200': log.status === 'error',
                                  'bg-zinc-100 text-zinc-600 border border-zinc-200': log.status === 'warning' || !log.status
                              }"
                              x-text="formatLabel(log.step_name) || 'STEP'"></span>

                        <!-- Message Content -->
                        <span class="flex-1 break-all text-zinc-700 group-hover:text-zinc-900"
                              :class="{
                                  'text-red-600 group-hover:text-red-700 font-medium': log.status === 'error',
                                  'text-zinc-900': log.status === 'success' || log.status === 'in_progress'
                              }"
                              x-text="log.message"></span>
                    </div>
                </template>

                <!-- Real-time beacon if still running (Monochrome pulse) -->
                <template x-if="deployment && (deployment.status === 'pending' || deployment.status === 'processing')">
                    <div class="flex items-center gap-2 pt-3 px-2 text-zinc-500 text-xs font-sans border-t border-zinc-100 mt-2">
                        <span class="size-2 rounded-full bg-zinc-900 animate-pulse"></span>
                        <span>Deployment in progress &middot; listening for live events...</span>
                    </div>
                </template>
            </div>
        </template>
    </div>
</div>
