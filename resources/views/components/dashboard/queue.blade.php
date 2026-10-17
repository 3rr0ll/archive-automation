<div>
    <h2 class="text-sm font-semibold text-zinc-900 mb-3">Queue</h2>
    <div class="bg-white border border-zinc-200 rounded-lg p-4">
        <div class="flex items-center justify-between mb-4">
            <span class="text-[13px] font-medium text-zinc-500">Processing</span>
            <span class="text-xs font-bold text-white bg-zinc-900 px-2 py-0.5 rounded-full" x-text="queueItems.length"></span>
        </div>
        
        <template x-if="queueItems.length === 0">
            <div class="py-6 text-center">
                <x-lucide-check-circle-2 class="size-6 text-zinc-300 mx-auto mb-2" />
                <p class="text-xs text-zinc-500 font-medium">All queues are clear</p>
                <p class="text-[11px] text-zinc-400 mt-0.5">No deployments running</p>
            </div>
        </template>

        <ul class="space-y-3.5" x-show="queueItems.length > 0">
            <template x-for="item in queueItems" :key="item.id || item.domain">
                <li class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3 text-zinc-700 min-w-0">
                        <!-- Circular Progress Bar -->
                        <div class="relative size-4 flex items-center justify-center shrink-0">
                            <svg class="size-4 -rotate-90 transform" viewBox="0 0 36 36">
                                <!-- Background Track -->
                                <path class="text-zinc-100" stroke-width="4" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                                <!-- Progress Indicator -->
                                <path class="text-zinc-900 transition-all duration-1000 ease-out" 
                                      :stroke-dasharray="`${getDeploymentProgress(item)}, 100`" 
                                      stroke-width="4" 
                                      stroke-linecap="round" 
                                      stroke="currentColor" 
                                      fill="none" 
                                      d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                            </svg>
                        </div>
                        <span class="truncate font-medium text-[13px]" x-text="item.domain"></span>
                    </div>
                    <span class="text-[11px] font-medium text-zinc-400 shrink-0 text-right capitalize" x-text="getStepLabel(item.current_step)"></span>
                </li>
            </template>
        </ul>
    </div>
</div>


