<div x-show="showModal" style="display: none;" class="relative z-50" aria-labelledby="slide-over-title" role="dialog" aria-modal="true">
    <!-- Clean backdrop -->
    <div x-show="showModal" 
         x-transition.opacity 
         class="fixed inset-0 bg-black/40 transition-opacity" 
         @click="closeModal()"></div>

    <div class="fixed inset-0 overflow-hidden">
        <div class="absolute inset-0 overflow-hidden">
            <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10 sm:pl-16">
                <div x-show="showModal" 
                     x-transition:enter="transform transition ease-in-out duration-200 sm:duration-300" 
                     x-transition:enter-start="translate-x-full" 
                     x-transition:enter-end="translate-x-0" 
                     x-transition:leave="transform transition ease-in-out duration-200 sm:duration-300" 
                     x-transition:leave-start="translate-x-0" 
                     x-transition:leave-end="translate-x-full" 
                     class="pointer-events-auto w-screen max-w-xl">
                    <div class="flex h-full flex-col bg-white border-l border-zinc-200">
                        <!-- Drawer Header -->
                        <div class="px-6 py-5 flex items-center justify-between border-b border-zinc-100">
                            <div>
                                <h2 class="text-base font-semibold text-zinc-900" id="slide-over-title">Add Server</h2>
                                <p class="text-xs text-zinc-500 mt-0.5">Register a new server by providing its details.</p>
                            </div>
                            <button type="button" @click="closeModal()" class="rounded-md text-zinc-400 hover:text-zinc-600 focus:outline-none transition p-1">
                                <span class="sr-only">Close panel</span>
                                <x-lucide-x class="size-4.5" />
                            </button>
                        </div>

                        <!-- Drawer Body -->
                        <div class="flex-1 overflow-y-auto px-6 py-6">
                            <form @submit.prevent="submitForm" id="server-form" class="space-y-6">
                                <!-- Server Name Field -->
                                <div>
                                    <label for="server-name" class="block text-xs font-semibold text-zinc-700 mb-1.5">Server Name</label>
                                    <input type="text" 
                                           id="server-name" 
                                           x-model="serverForm.name" 
                                           placeholder="e.g. Production Web-01" 
                                           required
                                           class="block w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 placeholder:text-zinc-400 focus:border-zinc-900 focus:outline-none focus:ring-1 focus:ring-zinc-900 sm:leading-6 transition">
                                    <template x-if="errors.name">
                                        <p class="text-xs text-red-500 mt-1" x-text="errors.name[0]"></p>
                                    </template>
                                </div>

                                <!-- Server ID Field -->
                                <div>
                                    <label for="server-id-input" class="block text-xs font-semibold text-zinc-700 mb-1.5">Server ID</label>
                                    <div class="relative">
                                        <input type="number" 
                                               id="server-id-input" 
                                               x-model="serverForm.server_id" 
                                               placeholder="e.g. 12345" 
                                               required
                                               min="1"
                                               class="block w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 placeholder:text-zinc-400 font-mono focus:border-zinc-900 focus:outline-none focus:ring-1 focus:ring-zinc-900 sm:leading-6 transition">
                                        <span class="absolute inset-y-0 right-3 flex items-center text-zinc-400 pointer-events-none text-xs font-mono">
                                            #
                                        </span>
                                    </div>
                                    <template x-if="errors.server_id">
                                        <p class="text-xs text-red-500 mt-1" x-text="errors.server_id[0]"></p>
                                    </template>
                                    <p class="text-[11px] text-zinc-500 mt-1">Unique numeric identifier for the server.</p>
                                </div>

                                <!-- Server IP Field -->
                                <div>
                                    <label for="server-ip-input" class="block text-xs font-semibold text-zinc-700 mb-1.5">Server IP</label>
                                    <div class="relative">
                                        <input type="text" 
                                               id="server-ip-input" 
                                               x-model="serverForm.ip_address" 
                                               placeholder="e.g. 192.168.1.1" 
                                               required
                                               class="block w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 placeholder:text-zinc-400 font-mono focus:border-zinc-900 focus:outline-none focus:ring-1 focus:ring-zinc-900 sm:leading-6 transition">
                                        <span class="absolute inset-y-0 right-3 flex items-center text-zinc-400 pointer-events-none text-xs font-mono">
                                            IP
                                        </span>
                                    </div>
                                    <template x-if="errors.ip_address">
                                        <p class="text-xs text-red-500 mt-1" x-text="errors.ip_address[0]"></p>
                                    </template>
                                    <p class="text-[11px] text-zinc-500 mt-1">Must be a valid IPv4 or IPv6 address.</p>
                                </div>

                                <!-- Status Toggle -->
                                <div>
                                    <label class="block text-xs font-semibold text-zinc-700 mb-1.5">Status</label>
                                    <div class="flex items-center gap-3">
                                        <button type="button" 
                                                @click="serverForm.is_active = !serverForm.is_active"
                                                class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-zinc-900 focus:ring-offset-2"
                                                :class="serverForm.is_active ? 'bg-zinc-900' : 'bg-zinc-200'" 
                                                role="switch" 
                                                :aria-checked="serverForm.is_active">
                                            <span aria-hidden="true" 
                                                  class="pointer-events-none inline-block size-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                                  :class="serverForm.is_active ? 'translate-x-5' : 'translate-x-0'"></span>
                                        </button>
                                        <span class="text-sm font-medium text-zinc-700" x-text="serverForm.is_active ? 'Active' : 'Inactive'"></span>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Drawer Footer -->
                        <div class="px-6 py-4 border-t border-zinc-100 flex items-center justify-end gap-3 bg-white">
                            <button type="button" 
                                    @click="closeModal()" 
                                    class="px-4 py-2 text-sm font-medium text-zinc-700 bg-white hover:bg-zinc-50 border border-zinc-200 rounded-md transition focus:outline-none cursor-pointer">
                                Cancel
                            </button>

                            <button type="submit" 
                                    form="server-form" 
                                    :disabled="isSubmitting" 
                                    class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium rounded-md text-white bg-zinc-900 hover:bg-zinc-800 focus:outline-none disabled:opacity-50 transition cursor-pointer gap-2">
                                <span x-show="isSubmitting" class="animate-spin inline-block size-3.5 border-2 border-white border-t-transparent rounded-full"></span>
                                <span x-show="!isSubmitting">Save Server</span>
                                <span x-show="isSubmitting">Saving</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
