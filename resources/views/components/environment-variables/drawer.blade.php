<div x-show="showModal" style="display: none;" class="relative z-50" aria-labelledby="slide-over-title" role="dialog" aria-modal="true">
    <!-- Clean backdrop without blur -->
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
                            <h2 class="text-base font-semibold text-zinc-900" id="slide-over-title">Add Environment Variable</h2>
                            <button type="button" @click="closeModal()" class="rounded-md text-zinc-400 hover:text-zinc-600 focus:outline-none transition p-1">
                                <span class="sr-only">Close panel</span>
                                <x-lucide-x class="size-4.5" />
                            </button>
                        </div>

                        <!-- Drawer Body -->
                        <div class="flex-1 overflow-y-auto px-6 py-6">
                            <form @submit.prevent="submitForm" id="env-var-form" class="space-y-6">
                                <!-- Type Selection -->
                                <div>
                                    <label class="block text-xs font-medium text-zinc-700 mb-2">Type</label>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <!-- Secret Option -->
                                        <label class="relative flex items-start justify-between p-3.5 rounded-lg border cursor-pointer transition-all text-left"
                                               :class="type === 'secret' ? 'border-blue-500 bg-blue-50/40 ring-1 ring-blue-500' : 'border-zinc-200 hover:border-zinc-300 bg-white'">
                                            <input type="radio" name="type" value="secret" x-model="type" class="sr-only">
                                            <div class="pr-2">
                                                <div class="text-sm font-semibold text-blue-600">Secret</div>
                                                <p class="text-[12px] text-blue-600/90 leading-relaxed mt-1">
                                                    You can't reveal this value after saving. Use for passwords, API keys, and tokens.
                                                </p>
                                            </div>
                                            <!-- Custom radio dot -->
                                            <div class="mt-0.5 shrink-0 size-4 rounded-full border flex items-center justify-center"
                                                 :class="type === 'secret' ? 'border-blue-600 bg-white' : 'border-zinc-300'">
                                                <span x-show="type === 'secret'" class="size-2 rounded-full bg-blue-600"></span>
                                            </div>
                                        </label>

                                        <!-- Config Option -->
                                        <label class="relative flex items-start justify-between p-3.5 rounded-lg border cursor-pointer transition-all text-left"
                                               :class="type === 'config' ? 'border-zinc-900 bg-zinc-50 ring-1 ring-zinc-900' : 'border-zinc-200 hover:border-zinc-300 bg-white'">
                                            <input type="radio" name="type" value="config" x-model="type" class="sr-only">
                                            <div class="pr-2">
                                                <div class="text-sm font-semibold text-zinc-900">Config</div>
                                                <p class="text-[12px] text-zinc-500 leading-relaxed mt-1">
                                                    Readable after saving for members with access. Use for non-sensitive values.
                                                </p>
                                            </div>
                                            <!-- Custom radio dot -->
                                            <div class="mt-0.5 shrink-0 size-4 rounded-full border flex items-center justify-center"
                                                 :class="type === 'config' ? 'border-zinc-900 bg-white' : 'border-zinc-300'">
                                                <span x-show="type === 'config'" class="size-2 rounded-full bg-zinc-900"></span>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <!-- Dynamic Variables List -->
                                <div class="space-y-6">
                                    <template x-for="(item, index) in variables" :key="index">
                                        <div class="space-y-4 pt-2 first:pt-0" :class="index > 0 ? 'border-t border-zinc-200' : ''">
                                            <!-- Row Header if multiple -->
                                            <div class="flex items-center justify-between" x-show="variables.length > 1">
                                                <span class="text-xs font-semibold text-zinc-500 uppercase tracking-wider" x-text="`Variable #${index + 1}`"></span>
                                                <button type="button" 
                                                        @click="removeVariableField(index)" 
                                                        class="text-zinc-400 hover:text-red-600 transition p-1" 
                                                        title="Remove variable">
                                                    <x-lucide-trash-2 class="size-4" />
                                                </button>
                                            </div>

                                            <!-- Key Field -->
                                            <div>
                                                <label :for="'key-' + index" class="block text-xs font-medium text-zinc-700 mb-1.5">Key</label>
                                                <input type="text" 
                                                       :id="'key-' + index" 
                                                       x-model="item.key" 
                                                       placeholder="CLIENT_KEY..." 
                                                       required
                                                       class="block w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 placeholder:text-zinc-400 focus:border-zinc-400 focus:outline-none focus:ring-0 sm:leading-6 font-mono transition">
                                            </div>

                                            <!-- Value Field with Reveal Toggle Eye -->
                                            <div>
                                                <label :for="'value-' + index" class="block text-xs font-medium text-zinc-700 mb-1.5">Value</label>
                                                <div class="relative">
                                                    <input :type="item.showValue ? 'text' : 'password'" 
                                                           :id="'value-' + index" 
                                                           x-model="item.value" 
                                                           placeholder="Enter a value" 
                                                           required
                                                           class="block w-full rounded-md border border-zinc-200 bg-white px-3 py-2 pr-10 text-sm text-zinc-900 placeholder:text-zinc-400 focus:border-zinc-400 focus:outline-none focus:ring-0 sm:leading-6 font-mono transition">
                                                    <button type="button" 
                                                            @click="toggleShowValue(index)" 
                                                            class="absolute inset-y-0 right-0 flex items-center pr-3 text-zinc-400 hover:text-zinc-600 transition"
                                                            title="Toggle value visibility">
                                                        <x-lucide-eye x-show="!item.showValue" class="size-4" />
                                                        <x-lucide-eye-off x-show="item.showValue" x-cloak class="size-4" />
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                <!-- Add New Variable Button -->
                                <div>
                                    <button type="button" 
                                            @click="addVariableField()" 
                                            class="w-full flex items-center justify-center gap-2 py-2 px-3 border border-zinc-200 rounded-md text-xs font-medium text-zinc-700 hover:bg-zinc-50 transition cursor-pointer">
                                        <x-lucide-plus class="size-3.5" />
                                        <span>Add new variable</span>
                                    </button>
                                </div>

                                <!-- Validation Errors -->
                                <template x-if="errors.key">
                                    <p class="text-xs text-red-500" x-text="errors.key[0]"></p>
                                </template>
                                <template x-if="errors.value">
                                    <p class="text-xs text-red-500" x-text="errors.value[0]"></p>
                                </template>
                            </form>
                        </div>

                        <!-- Drawer Footer -->
                        <div class="px-6 py-4 border-t border-zinc-100 flex items-center justify-between bg-white">
                            <div class="flex items-center gap-2 text-xs text-zinc-500">
                                <button type="button" class="inline-flex items-center gap-1.5 py-1.5 px-3 border border-zinc-200 rounded-md text-xs font-medium text-zinc-800 hover:bg-zinc-50 transition">
                                    <x-lucide-upload class="size-3.5" />
                                    Import .env
                                </button>
                                <span class="text-zinc-400 text-[11px] hidden sm:inline">or paste .env contents in Key input</span>
                            </div>

                            <button type="submit" 
                                    form="env-var-form" 
                                    :disabled="isSubmitting" 
                                    class="inline-flex items-center justify-center px-4 py-1.5 text-xs font-medium rounded-md text-white bg-zinc-900 hover:bg-zinc-800 focus:outline-none disabled:opacity-50 transition gap-2 cursor-pointer">
                                <span x-show="isSubmitting" class="animate-spin inline-block size-3.5 border-2 border-white border-t-transparent rounded-full"></span>
                                <x-lucide-save x-show="!isSubmitting" class="size-3.5" />
                                <span x-show="!isSubmitting">Save</span>
                                <span x-show="isSubmitting">Saving</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
