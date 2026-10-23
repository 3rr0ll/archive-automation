<div x-data="{
    open: false,
    title: '',
    message: '',
    key: '',
    fields: [],
    confirmText: 'Delete',
    cancelText: 'Cancel',
    type: 'danger', // 'danger' | 'primary'
    isProcessing: false,
    onConfirm: null,
    onCancel: null,

    init() {
        window.confirmModal = this;
        window.addEventListener('open-confirm-modal', (e) => {
            this.show(e.detail);
        });
    },

    show(options = {}) {
        this.title = options.title || 'Delete Confirmation';
        this.message = options.message || 'Are you sure you want to proceed? You cannot undo this action.';
        this.key = options.key || '';
        
        // Handle dynamic fields: [{ label: 'Server Name', value: '...' }, ...]
        if (Array.isArray(options.fields) && options.fields.length > 0) {
            this.fields = options.fields;
        } else if (options.key) {
            this.fields = [{ label: options.metaLabel || 'Key', value: options.key }];
        } else {
            this.fields = [];
        }

        this.confirmText = options.confirmText || 'Delete';
        this.cancelText = options.cancelText || 'Cancel';
        this.type = options.type || 'danger';
        this.onConfirm = typeof options.onConfirm === 'function' ? options.onConfirm : null;
        this.onCancel = typeof options.onCancel === 'function' ? options.onCancel : null;
        this.isProcessing = false;
        this.open = true;
    },

    close() {
        if (this.isProcessing) return;
        this.open = false;
        if (this.onCancel) {
            this.onCancel();
        }
    },

    async confirm() {
        if (this.onConfirm) {
            try {
                this.isProcessing = true;
                await this.onConfirm();
                this.open = false;
            } catch (err) {
                console.error(err);
            } finally {
                this.isProcessing = false;
            }
        } else {
            this.open = false;
        }
    }
}"
x-show="open"
x-cloak
style="display: none;"
class="relative z-50"
aria-labelledby="confirm-modal-title"
role="dialog"
aria-modal="true"
@keydown.escape.window="close()">

    <!-- Clean backdrop without blur -->
    <div x-show="open"
         x-transition.opacity
         class="fixed inset-0 bg-black/40 transition-opacity"
         @click="close()"></div>

    <!-- Centered Modal Container -->
    <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
            <div x-show="open"
                 x-transition:enter="ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="relative transform overflow-hidden rounded-2xl bg-white text-left transition-all sm:my-8 sm:w-full sm:max-w-lg border border-zinc-200 p-6 space-y-5"
                 @click.stop>

                <!-- Header Title & Subtitle -->
                <div>
                    <h3 class="text-lg font-bold text-zinc-950 tracking-tight" id="confirm-modal-title" x-text="title"></h3>
                    <p class="text-sm text-zinc-500 mt-2 leading-relaxed" x-text="message"></p>
                </div>

                <!-- Dynamic Fields Display -->
                <template x-if="fields && fields.length > 0">
                    <div class="space-y-3">
                        <template x-for="(field, fIdx) in fields" :key="fIdx">
                            <div class="space-y-1.5">
                                <label class="block text-xs font-semibold text-zinc-600" x-text="field.label || 'Value'"></label>
                                <div class="w-full rounded-lg border border-zinc-200 bg-zinc-50/50 px-3.5 py-2 text-sm text-zinc-900 font-mono" x-text="field.value"></div>
                            </div>
                        </template>
                    </div>
                </template>

                <!-- Footer Actions: Cancel on the left, Action on the right -->
                <div class="pt-4 flex items-center justify-between border-t border-zinc-100">
                    <button type="button"
                            @click="close()"
                            :disabled="isProcessing"
                            class="inline-flex items-center gap-1.5 justify-center rounded-lg bg-white px-4 py-2 text-sm font-medium text-zinc-800 border border-zinc-200 hover:bg-zinc-50 transition focus:outline-none disabled:opacity-50 cursor-pointer">
                        <x-lucide-x class="size-4 text-zinc-500" />
                        <span x-text="cancelText">Cancel</span>
                    </button>

                    <button type="button"
                            @click="confirm()"
                            :disabled="isProcessing"
                            class="inline-flex items-center justify-center gap-1.5 rounded-lg px-5 py-2 text-sm font-medium text-white transition focus:outline-none disabled:opacity-50 cursor-pointer"
                            :class="type === 'danger' ? 'bg-[#DC2626] hover:bg-red-700' : 'bg-zinc-900 hover:bg-zinc-800'">
                        <template x-if="!isProcessing">
                            <span class="inline-flex items-center gap-1.5">
                                <x-lucide-trash-2 x-show="type === 'danger'" class="size-4" />
                                <x-lucide-check x-show="type !== 'danger'" class="size-4" />
                                <span x-text="confirmText">Delete</span>
                            </span>
                        </template>
                        <span x-show="isProcessing" class="inline-flex items-center gap-2">
                            <span class="animate-spin inline-block size-3.5 border-2 border-white border-t-transparent rounded-full"></span>
                            <span>Processing</span>
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
