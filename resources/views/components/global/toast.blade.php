<div x-data="{
    toasts: [],
    
    init() {
        window.toast = {
            show: (message, type = 'success', duration = 3000) => this.add(message, type, duration),
            success: (message, duration = 3000) => this.add(message, 'success', duration),
            error: (message, duration = 4000) => this.add(message, 'error', duration),
            info: (message, duration = 3000) => this.add(message, 'info', duration),
            warning: (message, duration = 3500) => this.add(message, 'warning', duration),
        };

        window.addEventListener('toast', (e) => {
            const detail = e.detail || {};
            this.add(
                detail.message || (typeof detail === 'string' ? detail : ''),
                detail.type || 'success',
                detail.duration || 3000
            );
        });
    },

    add(message, type = 'success', duration = 3000) {
        if (!message) return;
        const id = Date.now() + Math.random().toString(36).substring(2, 9);
        const toast = { id, message, type };
        this.toasts.push(toast);

        if (duration > 0) {
            setTimeout(() => {
                this.remove(id);
            }, duration);
        }
    },

    remove(id) {
        this.toasts = this.toasts.filter(t => t.id !== id);
    }
}"
class="fixed bottom-5 right-5 z-50 flex flex-col gap-2.5 max-w-sm w-full pointer-events-none"
role="region"
aria-live="polite">
    <template x-for="item in toasts" :key="item.id">
        <div x-show="true"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-3 scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0 scale-100"
             x-transition:leave-end="opacity-0 translate-y-2 scale-95"
             class="pointer-events-auto flex items-center justify-between gap-3 px-4 py-3 rounded-lg border bg-white shadow-lg text-sm text-zinc-900 border-zinc-200 font-sans">
            
            <div class="flex items-center gap-2.5 min-w-0">
                <!-- Status Icons -->
                <template x-if="item.type === 'success'">
                    <span class="size-5 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0 border border-emerald-200/60">
                        <x-lucide-check class="size-3 stroke-[2.5]" />
                    </span>
                </template>

                <template x-if="item.type === 'error'">
                    <span class="size-5 rounded-full bg-red-50 text-red-600 flex items-center justify-center shrink-0 border border-red-200/60">
                        <x-lucide-alert-circle class="size-3 stroke-[2.5]" />
                    </span>
                </template>

                <template x-if="item.type === 'warning'">
                    <span class="size-5 rounded-full bg-amber-50 text-amber-600 flex items-center justify-center shrink-0 border border-amber-200/60">
                        <x-lucide-alert-triangle class="size-3 stroke-[2.5]" />
                    </span>
                </template>

                <template x-if="item.type === 'info'">
                    <span class="size-5 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center shrink-0 border border-blue-200/60">
                        <x-lucide-info class="size-3 stroke-[2.5]" />
                    </span>
                </template>

                <span class="truncate font-medium text-xs text-zinc-800" x-text="item.message"></span>
            </div>

            <!-- Dismiss Button -->
            <button type="button" 
                    @click="remove(item.id)" 
                    class="text-zinc-400 hover:text-zinc-600 rounded p-0.5 transition cursor-pointer shrink-0"
                    title="Dismiss">
                <x-lucide-x class="size-3.5" />
            </button>
        </div>
    </template>
</div>
