<div x-show="showModal" 
     x-cloak 
     class="relative z-50" 
     aria-labelledby="slide-over-title" 
     role="dialog" 
     aria-modal="true">
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
                            <div>
                                <h2 class="text-base font-semibold text-zinc-900" id="slide-over-title">New Deployment</h2>
                                <p class="text-xs text-zinc-500 mt-0.5">Deploy an archived website or custom ZIP onto your servers.</p>
                            </div>
                            <button type="button" @click="closeModal()" class="rounded-md text-zinc-400 hover:text-zinc-600 focus:outline-none transition p-1">
                                <span class="sr-only">Close panel</span>
                                <x-lucide-x class="size-4.5" />
                            </button>
                        </div>

                        <!-- Drawer Body -->
                        <div class="flex-1 overflow-y-auto px-6 py-6">
                            <form @submit.prevent="submitDeployment" id="deployment-form" class="space-y-6">
                                <!-- Domain Field -->
                                <div>
                                    <label for="deployment-domain" class="block text-xs font-medium text-zinc-700 mb-1.5">
                                        Domain Name <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" 
                                           id="deployment-domain" 
                                           x-model="form.domain" 
                                           placeholder="example.com" 
                                           required
                                           class="block w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 placeholder:text-zinc-400 focus:border-zinc-400 focus:outline-none focus:ring-0 sm:leading-6 transition font-mono">
                                    <template x-if="errors.domain">
                                        <p class="text-xs text-red-500 mt-1" x-text="errors.domain[0]"></p>
                                    </template>
                                </div>

                                <!-- Server Selection -->
                                <div>
                                    <label class="block text-xs font-medium text-zinc-700 mb-1.5">
                                        Target Server <span class="text-red-500">*</span>
                                    </label>
                                    
                                    <!-- Custom Dropdown Matching Design System -->
                                    <div class="relative">
                                        <button type="button" 
                                                @click="serverDropdownOpen = !serverDropdownOpen" 
                                                @click.away="serverDropdownOpen = false" 
                                                class="flex items-center justify-between w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-zinc-400 focus:outline-none transition cursor-pointer"
                                                :class="serverDropdownOpen ? 'border-zinc-400 ring-1 ring-zinc-400/20' : ''">
                                            <div class="flex items-center gap-2 min-w-0">
                                                <x-lucide-server class="size-4 text-zinc-400 shrink-0" />
                                                <span class="truncate" x-show="selectedServer" x-text="selectedServer ? `${selectedServer.name} (${selectedServer.ip_address || 'No IP'})` : ''"></span>
                                                <span class="text-zinc-400" x-show="!selectedServer">Select an active server</span>
                                            </div>
                                            <x-lucide-chevron-down class="size-4 text-zinc-400 shrink-0 transition-transform duration-200" ::class="serverDropdownOpen ? 'rotate-180' : ''" />
                                        </button>

                                        <!-- Dropdown Menu -->
                                        <div x-show="serverDropdownOpen" 
                                             x-transition.opacity
                                             style="display: none;" 
                                             class="absolute left-0 right-0 z-50 mt-1 max-h-60 overflow-y-auto rounded-md bg-white py-1 border border-zinc-200 shadow-lg text-sm focus:outline-none" 
                                             role="menu">
                                            <template x-for="server in servers" :key="server.server_id">
                                                <button type="button" 
                                                        @click="form.server_id = server.server_id; serverDropdownOpen = false;" 
                                                        class="block w-full text-left px-4 py-2 text-sm text-zinc-700 hover:bg-zinc-100 cursor-pointer"
                                                        :class="form.server_id == server.server_id ? 'bg-zinc-50 font-medium text-zinc-900' : ''"
                                                        role="menuitem">
                                                    <span x-text="server.name"></span>
                                                    <span class="text-xs text-zinc-400 font-mono ml-1" x-text="`(${server.ip_address || 'No IP'})`"></span>
                                                </button>
                                            </template>
                                            <div x-show="servers.length === 0" class="px-4 py-2 text-sm text-zinc-400 text-center">
                                                No active servers available.
                                            </div>
                                        </div>
                                    </div>

                                    <template x-if="servers.length === 0">
                                        <p class="text-[11px] text-zinc-400 mt-1">No active servers available. Please add or sync a server first.</p>
                                    </template>
                                    <template x-if="errors.server_id">
                                        <p class="text-xs text-red-500 mt-1" x-text="errors.server_id[0]"></p>
                                    </template>
                                </div>

                                <!-- Domain Version (www vs non-www) Selection -->
                                <div>
                                    <label class="block text-xs font-medium text-zinc-700 mb-2">Domain Version</label>
                                    <div class="grid grid-cols-2 gap-3">
                                        <!-- Non-WWW Option -->
                                        <label class="relative flex items-center justify-between p-3 rounded-lg border cursor-pointer transition-all text-left"
                                               :class="form.version === 'non-www' ? 'border-zinc-900 bg-zinc-50 ring-1 ring-zinc-900' : 'border-zinc-200 hover:border-zinc-300 bg-white'">
                                            <input type="radio" name="domain_version" value="non-www" x-model="form.version" class="sr-only">
                                            <div>
                                                <div class="text-xs font-semibold text-zinc-900 font-mono">non-www</div>
                                                <div class="text-[11px] text-zinc-500 mt-0.5">example.com</div>
                                            </div>
                                            <div class="shrink-0 size-4 rounded-full border flex items-center justify-center"
                                                 :class="form.version === 'non-www' ? 'border-zinc-900 bg-white' : 'border-zinc-300'">
                                                <span x-show="form.version === 'non-www'" class="size-2 rounded-full bg-zinc-900"></span>
                                            </div>
                                        </label>

                                        <!-- WWW Option -->
                                        <label class="relative flex items-center justify-between p-3 rounded-lg border cursor-pointer transition-all text-left"
                                               :class="form.version === 'www' ? 'border-zinc-900 bg-zinc-50 ring-1 ring-zinc-900' : 'border-zinc-200 hover:border-zinc-300 bg-white'">
                                            <input type="radio" name="domain_version" value="www" x-model="form.version" class="sr-only">
                                            <div>
                                                <div class="text-xs font-semibold text-zinc-900 font-mono">www</div>
                                                <div class="text-[11px] text-zinc-500 mt-0.5">www.example.com</div>
                                            </div>
                                            <div class="shrink-0 size-4 rounded-full border flex items-center justify-center"
                                                 :class="form.version === 'www' ? 'border-zinc-900 bg-white' : 'border-zinc-300'">
                                                <span x-show="form.version === 'www'" class="size-2 rounded-full bg-zinc-900"></span>
                                            </div>
                                        </label>
                                    </div>
                                    <template x-if="errors.version">
                                        <p class="text-xs text-red-500 mt-1" x-text="errors.version[0]"></p>
                                    </template>
                                </div>

                                <!-- Archive Source Selection: Wayback URL vs Upload File -->
                                <div>
                                    <label class="block text-xs font-medium text-zinc-700 mb-2">Archive Source</label>
                                    <div class="grid grid-cols-2 gap-3 mb-4">
                                        <button type="button" 
                                                @click="form.source_type = 'url'" 
                                                class="flex items-center justify-center gap-2 p-2.5 rounded-lg border text-xs font-medium transition"
                                                :class="form.source_type === 'url' ? 'border-zinc-900 bg-zinc-900 text-white' : 'border-zinc-200 text-zinc-700 hover:bg-zinc-50 bg-white'">
                                            <x-lucide-globe class="size-3.5" />
                                            <span>Wayback URL</span>
                                        </button>
                                        <button type="button" 
                                                @click="form.source_type = 'upload'" 
                                                class="flex items-center justify-center gap-2 p-2.5 rounded-lg border text-xs font-medium transition"
                                                :class="form.source_type === 'upload' ? 'border-zinc-900 bg-zinc-900 text-white' : 'border-zinc-200 text-zinc-700 hover:bg-zinc-50 bg-white'">
                                            <x-lucide-upload class="size-3.5" />
                                            <span>ZIP Archive</span>
                                        </button>
                                    </div>

                                    <!-- Archive URL Input -->
                                    <div x-show="form.source_type === 'url'" class="space-y-1.5">
                                        <label for="archive-url" class="block text-xs font-medium text-zinc-700">Wayback Machine URL (optional)</label>
                                        <input type="url" 
                                               id="archive-url" 
                                               x-model="form.archive_url" 
                                               placeholder="https://web.archive.org/web/..." 
                                               class="block w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 placeholder:text-zinc-400 focus:border-zinc-400 focus:outline-none focus:ring-0 sm:leading-6 font-mono transition">
                                        <p class="text-[11px] text-zinc-400">Leave blank to automatically locate the best snapshot on Wayback Machine.</p>
                                        <template x-if="errors.archive_url">
                                            <p class="text-xs text-red-500 mt-1" x-text="errors.archive_url[0]"></p>
                                        </template>
                                    </div>

                                    <!-- Archive File Upload Input -->
                                    <div x-show="form.source_type === 'upload'" class="space-y-2">
                                        <label class="block text-xs font-medium text-zinc-700">Upload ZIP Archive</label>
                                        <div class="border-2 border-dashed border-zinc-200 rounded-lg p-5 text-center hover:border-zinc-300 transition bg-zinc-50/50">
                                            <input type="file" 
                                                   id="archive-file-input" 
                                                   accept=".zip" 
                                                   @change="handleFileUpload($event)" 
                                                   class="hidden">
                                            <label for="archive-file-input" class="cursor-pointer flex flex-col items-center">
                                                <x-lucide-folder-archive class="size-8 text-zinc-400 mb-2" />
                                                <span class="text-xs font-semibold text-zinc-800" x-text="form.archiveFile ? form.archiveFile.name : 'Choose a ZIP file'"></span>
                                                <span class="text-[11px] text-zinc-400 mt-1" x-show="!form.archiveFile">Maximum size: 512 MB (.zip)</span>
                                                <span class="text-[11px] text-zinc-500 mt-1" x-show="form.archiveFile" x-text="`${(form.archiveFile?.size / 1024 / 1024).toFixed(2)} MB`"></span>
                                            </label>
                                            <template x-if="form.archiveFile">
                                                <button type="button" @click="form.archiveFile = null; $el.closest('div').querySelector('input').value = ''" class="mt-2 text-xs text-red-600 hover:underline">Remove file</button>
                                            </template>
                                            <div x-show="isSubmitting && uploadProgress > 0" class="mt-3">
                                                <div class="h-1.5 overflow-hidden rounded-full bg-zinc-200">
                                                    <div class="h-full bg-zinc-900 transition-all" :style="`width: ${uploadProgress}%`"></div>
                                                </div>
                                                <p class="mt-1 text-[11px] text-zinc-500" x-text="`Uploading ZIP: ${uploadProgress}%`"></p>
                                            </div>
                                        </div>
                                        <template x-if="errors.archive">
                                            <p class="text-xs text-red-500 mt-1" x-text="errors.archive[0]"></p>
                                        </template>
                                    </div>
                                </div>

                                <!-- Generic Errors -->
                                <template x-if="errors.general">
                                    <div class="p-3 rounded-lg bg-red-50 border border-red-200">
                                        <p class="text-xs text-red-600" x-text="errors.general"></p>
                                    </div>
                                </template>
                            </form>
                        </div>

                        <!-- Drawer Footer -->
                        <div class="px-6 py-4 border-t border-zinc-100 flex items-center justify-between bg-white">
                            <button type="button" 
                                    @click="closeModal()" 
                                    class="inline-flex items-center justify-center px-3.5 py-1.5 text-xs font-medium rounded-md text-zinc-700 hover:bg-zinc-100 transition border border-zinc-200">
                                Cancel
                            </button>

                            <button type="submit" 
                                    form="deployment-form" 
                                    :disabled="isSubmitting" 
                                    class="inline-flex items-center justify-center px-4 py-1.5 text-xs font-medium rounded-md text-white bg-zinc-900 hover:bg-zinc-800 focus:outline-none disabled:opacity-50 transition cursor-pointer">
                                <span x-show="!isSubmitting">Deploy Domain</span>
                                <span x-show="isSubmitting" class="flex items-center gap-1.5">
                                    <span class="animate-spin inline-block size-3.5 border-2 border-white border-t-transparent rounded-full"></span>
                                    <span x-text="uploadProgress > 0 && uploadProgress < 100 ? `Uploading ${uploadProgress}%` : 'Deploying'"></span>
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
