<div class="bg-white border border-zinc-200 rounded-lg">
    <ul role="list">
        <!-- Dynamic list rendering from Alpine items state -->
        <template x-for="(item, index) in filteredItems" :key="item.id">
            <li class="bg-white hover:bg-zinc-50/50 transition-colors relative"
                x-data="{ open: false }"
                :class="[
                    index === 0 ? 'rounded-t-lg' : '',
                    index === filteredItems.length - 1 ? 'rounded-b-lg' : 'border-b border-zinc-200',
                    open ? 'z-30' : 'z-10'
                ]">
                <!-- Row Header (Always visible) -->
                <div class="px-4 py-4 sm:px-6 flex items-center justify-between"
                     :class="[
                         index === 0 ? 'rounded-t-lg' : '',
                         index === filteredItems.length - 1 && editingId !== item.id ? 'rounded-b-lg' : '',
                         editingId === item.id ? 'border-b border-zinc-100 bg-zinc-50/40' : ''
                     ]">
                    <div class="flex items-center gap-3 flex-1 min-w-0" x-data="{ showConfigValue: false }">
                        <div class="flex-shrink-0 text-zinc-400">
                            <x-lucide-lock class="h-4 w-4" />
                        </div>
                        <div class="flex items-center gap-2.5 min-w-0">
                            <p class="text-sm font-medium text-zinc-900 font-mono truncate" x-text="item.key"></p>
                            
                            <!-- Config Eye Icon toggle & Value Display -->
                            <template x-if="item.type === 'config'">
                                <div class="flex items-center gap-1.5">
                                    <button type="button" 
                                            @click="showConfigValue = !showConfigValue" 
                                            class="text-zinc-400 hover:text-zinc-600 focus:outline-none p-0.5 rounded transition-colors cursor-pointer"
                                            :title="showConfigValue ? 'Hide value' : 'Show value'">
                                        <x-lucide-eye x-show="!showConfigValue" class="h-3.5 w-3.5" />
                                        <x-lucide-eye-off x-show="showConfigValue" style="display: none;" class="h-3.5 w-3.5" />
                                    </button>
                                    <span x-show="showConfigValue" 
                                          style="display: none;" 
                                          class="text-xs font-mono text-zinc-600 bg-zinc-100 px-2 py-0.5 rounded border border-zinc-200 truncate max-w-xs"
                                          x-text="item.value || ''"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                    <div class="flex items-center justify-between gap-6 flex-shrink-0">
                        <div class="hidden sm:flex items-center gap-4 text-xs text-zinc-500 whitespace-nowrap">
                            <span x-text="`Added ${formatDate(item.created_at)}`"></span>
                        </div>

                        <!-- Dropdown Menu -->
                        <div class="relative">
                            <button type="button" 
                                    @click="open = !open" 
                                    @click.away="open = false" 
                                    class="text-zinc-400 hover:text-zinc-600 focus:outline-none rounded p-1 hover:bg-zinc-100 transition-colors cursor-pointer">
                                <x-lucide-ellipsis-vertical class="h-4 w-4" />
                            </button>
                            <div x-show="open" 
                                 x-transition.opacity 
                                 style="display: none;" 
                                 class="absolute right-0 z-50 w-48 rounded-md bg-white py-1 border border-zinc-200 focus:outline-none shadow-lg"
                                 :class="items.length > 1 && index === items.length - 1 ? 'bottom-full mb-2 origin-bottom-right' : 'top-full mt-2 origin-top-right'" 
                                 role="menu">
                                <button type="button" 
                                        @click="copyKey(item.key); open = false;" 
                                        class="block w-full text-left px-4 py-2 text-sm text-zinc-700 hover:bg-zinc-100" 
                                        role="menuitem">
                                    Copy Name
                                </button>
                                <button type="button" 
                                        @click="startEdit(item); open = false;" 
                                        class="block w-full text-left px-4 py-2 text-sm text-zinc-700 hover:bg-zinc-100" 
                                        role="menuitem">
                                    Edit
                                </button>
                                <button type="button" 
                                        @click="deleteVariable(item.id); open = false;" 
                                        class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50" 
                                        role="menuitem">
                                    Delete
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Expanded Inline Edit Form View Below Header -->
                <div x-show="editingId === item.id" 
                     style="display: none;" 
                     class="p-6 bg-white space-y-6"
                     :class="index === items.length - 1 ? 'rounded-b-lg' : ''">
                    <!-- Key Input (Locked/Read-only) -->
                    <div>
                        <label class="block text-xs font-semibold text-zinc-700 mb-1.5">Key</label>
                        <div class="relative">
                            <input type="text" 
                                   :value="editForm.key" 
                                   readonly 
                                   disabled
                                   class="block w-full rounded-md border border-zinc-200 bg-zinc-50 px-3.5 py-2.5 text-sm text-zinc-500 font-mono cursor-not-allowed select-none focus:outline-none">
                            <span class="absolute inset-y-0 right-3 flex items-center text-zinc-400 pointer-events-none text-xs font-semibold">
                                A
                            </span>
                        </div>
                    </div>

                    <!-- Value Input (Editable) -->
                    <div>
                        <label class="block text-xs font-semibold text-zinc-700 mb-1.5">Value</label>
                        <textarea rows="3" 
                                  x-model="editForm.value" 
                                  :placeholder="editForm.type === 'secret' ? 'Enter a new secret value to replace it (existing secret is hidden)' : 'Enter value'" 
                                  class="block w-full rounded-md border border-zinc-200 bg-white px-3.5 py-2.5 text-sm text-zinc-900 placeholder:text-zinc-400 font-mono focus:border-zinc-400 focus:outline-none focus:ring-0 transition resize-y"></textarea>
                        <template x-if="editForm.type === 'secret'">
                            <p class="text-[11px] text-zinc-500 mt-1">Secret values are encrypted and cannot be viewed. Leave blank to keep existing value, or enter a new value to update.</p>
                        </template>
                        <template x-if="editForm.errors.value">
                            <p class="text-xs text-red-500 mt-1" x-text="editForm.errors.value[0]"></p>
                        </template>
                    </div>

                    <!-- Type Radio Cards (Locked) -->
                    <div>
                        <label class="block text-xs font-semibold text-zinc-700 mb-2">Type</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 opacity-95">
                            <!-- Secret Card -->
                            <div class="relative flex items-start justify-between p-3.5 rounded-lg border text-left cursor-not-allowed select-none transition-all"
                                 :class="editForm.type === 'secret' ? 'border-blue-500 bg-white ring-1 ring-blue-500' : 'border-zinc-200 bg-zinc-50/50 opacity-60'">
                                <div class="pr-2">
                                    <div class="text-sm font-semibold" :class="editForm.type === 'secret' ? 'text-blue-600' : 'text-zinc-700'">Secret</div>
                                    <p class="text-xs text-zinc-500 leading-relaxed mt-0.5">
                                        You can't reveal this value after saving. Use for passwords, API keys, and tokens.
                                    </p>
                                </div>
                                <div class="mt-0.5 shrink-0 size-4 rounded-full border flex items-center justify-center"
                                     :class="editForm.type === 'secret' ? 'border-blue-600 bg-white' : 'border-zinc-300'">
                                    <span x-show="editForm.type === 'secret'" class="size-2 rounded-full bg-blue-600"></span>
                                </div>
                            </div>

                            <!-- Config Card -->
                            <div class="relative flex items-start justify-between p-3.5 rounded-lg border text-left cursor-not-allowed select-none transition-all"
                                 :class="editForm.type === 'config' ? 'border-blue-500 bg-white ring-1 ring-blue-500' : 'border-zinc-200 bg-zinc-50/50 opacity-60'">
                                <div class="pr-2">
                                    <div class="text-sm font-semibold" :class="editForm.type === 'config' ? 'text-blue-600' : 'text-zinc-700'">Config</div>
                                    <p class="text-xs text-zinc-500 leading-relaxed mt-0.5">
                                        Readable after saving for members with access. Use for non-sensitive values.
                                    </p>
                                </div>
                                <div class="mt-0.5 shrink-0 size-4 rounded-full border flex items-center justify-center"
                                     :class="editForm.type === 'config' ? 'border-blue-600 bg-white' : 'border-zinc-300'">
                                    <span x-show="editForm.type === 'config'" class="size-2 rounded-full bg-blue-600"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons: Cancel and Save -->
                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-zinc-100">
                        <button type="button" 
                                @click="cancelEdit()" 
                                class="px-4 py-2 text-sm font-medium text-zinc-700 bg-white hover:bg-zinc-50 border border-zinc-200 rounded-md transition focus:outline-none">
                            Cancel
                        </button>
                        <button type="button" 
                                @click="saveEdit()" 
                                :disabled="editForm.isSaving" 
                                class="inline-flex items-center justify-center px-5 py-2 text-sm font-medium text-white bg-zinc-900 hover:bg-zinc-800 disabled:opacity-50 rounded-md transition focus:outline-none">
                            <span x-show="!editForm.isSaving">Save</span>
                            <span x-show="editForm.isSaving">Saving...</span>
                        </button>
                    </div>
                </div>
            </li>
        </template>

        <!-- Empty State -->
        <template x-if="filteredItems.length === 0">
            <li class="px-6 py-12 text-center text-zinc-500 text-sm">
                <div class="flex flex-col items-center justify-center">
                    <div class="size-10 rounded-full bg-zinc-100 flex items-center justify-center mb-3">
                        <x-lucide-lock class="size-5 text-zinc-400" />
                    </div>
                    <span class="font-medium text-zinc-700 mb-1">No variables found</span>
                    <span class="text-xs text-zinc-400" x-text="searchQuery || typeFilter !== 'all' ? 'Try adjusting your search or filters.' : 'Get started by adding your first variable.'"></span>
                </div>
            </li>
        </template>
    </ul>
</div>
