<div x-data="queueData()">
    <h2 class="text-sm font-semibold text-zinc-900 mb-3">Queue</h2>
    <div class="bg-white border border-zinc-200 rounded-lg p-4">
        <div class="flex items-center justify-between mb-4">
            <span class="text-[13px] font-medium text-zinc-500">Processing</span>
            <span class="text-xs font-bold text-white bg-zinc-900 px-2 py-0.5 rounded-full" x-text="items.length"></span>
        </div>
        
        <ul class="space-y-3.5">
            <template x-for="item in items" :key="item.domain">
                <li class="flex items-center justify-between">
                    <div class="flex items-center gap-3 text-zinc-700">
                        <!-- Circular Progress Bar -->
                        <div class="relative size-4 flex items-center justify-center shrink-0">
                            <svg class="size-4 -rotate-90 transform" viewBox="0 0 36 36">
                                <!-- Background Track -->
                                <path class="text-zinc-100" stroke-width="4" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                                <!-- Progress Indicator -->
                                <path class="text-zinc-900 transition-all duration-1000 ease-out" :stroke-dasharray="`${item.progress}, 100`" stroke-width="4" stroke-linecap="round" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                            </svg>
                        </div>
                        <span class="truncate font-medium text-[13px]" x-text="item.domain"></span>
                    </div>
                    <span class="text-[11px] font-medium text-zinc-400" x-text="item.status"></span>
                </li>
            </template>
        </ul>
    </div>
</div>

