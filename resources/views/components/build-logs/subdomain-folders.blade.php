<div x-show="subdomainFoldersMeta.total > 0"
     x-cloak
     class="shrink-0 bg-white dark:bg-[#18181b] border border-zinc-200 dark:border-zinc-800 rounded-lg overflow-hidden transition-colors">

    <!-- Card Header with Select All, Preview, and Apply actions -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-4 py-3 border-b border-zinc-200 dark:border-zinc-800 bg-zinc-50/60 dark:bg-[#18181b]">
        <div class="min-w-0">
            <div class="flex items-center gap-2">
                <span class="text-xs font-semibold text-zinc-900 dark:text-zinc-100">Detected subdomain folders</span>
                <span class="text-[11px] font-medium text-zinc-500 dark:text-zinc-400"
                      x-text="`(${subdomainFoldersMeta.total} found)`"></span>
            </div>
            <p class="text-[11px] text-zinc-500 dark:text-zinc-400 mt-0.5">
                Select folders to generate subdomain rewrite rules in <span class="font-mono">.htaccess</span>.
            </p>
        </div>

        <div class="flex items-center flex-wrap gap-2">
            <!-- Select all / Deselect all toggle -->
            <button type="button"
                    @click="toggleSelectAll"
                    class="text-xs font-medium text-zinc-600 dark:text-zinc-300 hover:text-zinc-900 dark:hover:text-zinc-100 flex items-center gap-1.5 px-2.5 py-1 rounded border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 hover:bg-zinc-50 dark:hover:bg-zinc-800 transition cursor-pointer">
                <input type="checkbox"
                       :checked="isAllSubdomainsSelected"
                       :indeterminate.prop="isSubdomainsIndeterminate"
                       class="size-3.5 rounded border-zinc-300 dark:border-zinc-700 text-indigo-600 pointer-events-none">
                <span x-text="isAllSubdomainsSelected ? 'Deselect all' : 'Select all'"></span>
            </button>

            <!-- Preview Rules Button -->
            <button type="button"
                    @click="previewHtaccessRules"
                    :disabled="selectedSubdomainFolders.length === 0 || isPreviewingHtaccess"
                    :class="selectedSubdomainFolders.length === 0 || isPreviewingHtaccess
                        ? 'opacity-40 cursor-not-allowed text-zinc-400 dark:text-zinc-600'
                        : 'hover:bg-zinc-100 dark:hover:bg-zinc-800 text-zinc-700 dark:text-zinc-300 cursor-pointer'"
                    class="px-2.5 py-1 text-xs font-medium rounded border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 inline-flex items-center gap-1.5 transition">
                <template x-if="isPreviewingHtaccess">
                    <span class="animate-spin inline-block size-3.5 border-2 border-zinc-400 border-t-zinc-800 rounded-full"></span>
                </template>
                <template x-if="!isPreviewingHtaccess">
                    <x-lucide-eye class="size-3.5" />
                </template>
                <span>Preview</span>
            </button>

            <!-- Apply to .htaccess Button -->
            <button type="button"
                    @click="applyHtaccessRules"
                    :disabled="selectedSubdomainFolders.length === 0 || isApplyingHtaccess"
                    :class="selectedSubdomainFolders.length === 0 || isApplyingHtaccess
                        ? 'opacity-40 cursor-not-allowed bg-zinc-300 dark:bg-zinc-800 text-zinc-500'
                        : 'bg-zinc-900 hover:bg-zinc-800 text-white dark:bg-zinc-100 dark:hover:bg-white dark:text-zinc-900 shadow-sm cursor-pointer'"
                    class="px-3 py-1 text-xs font-semibold rounded inline-flex items-center gap-1.5 transition">
                <template x-if="isApplyingHtaccess">
                    <span class="animate-spin inline-block size-3.5 border-2 border-white/30 border-t-white dark:border-zinc-900/30 dark:border-t-zinc-900 rounded-full"></span>
                </template>
                <template x-if="!isApplyingHtaccess">
                    <x-lucide-file-code class="size-3.5" />
                </template>
                <span x-text="isApplyingHtaccess ? 'Applying...' : 'Apply to .htaccess'"></span>
            </button>
        </div>
    </div>

    <!-- Feedback Notice / Success / Error Alert -->
    <template x-if="htaccessFeedback">
        <div class="px-4 py-2.5 border-b text-xs transition"
             :class="htaccessFeedback.type === 'error'
                 ? 'bg-rose-50 dark:bg-rose-950/40 border-rose-200 dark:border-rose-900/60 text-rose-800 dark:text-rose-200'
                 : 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-900/60 text-emerald-900 dark:text-emerald-100'">
            <div class="flex items-start justify-between gap-3">
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <template x-if="htaccessFeedback.type === 'error'">
                            <x-lucide-alert-triangle class="size-4 shrink-0 text-rose-600 dark:text-rose-400" />
                        </template>
                        <template x-if="htaccessFeedback.type !== 'error'">
                            <x-lucide-check-circle-2 class="size-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                        </template>
                        <span class="font-medium" x-text="htaccessFeedback.message"></span>
                    </div>

                    <!-- Change details pills -->
                    <div class="flex items-center flex-wrap gap-1.5 pt-0.5 text-[11px]" x-show="htaccessFeedback.type !== 'error'">
                        <template x-if="htaccessFeedback.added && htaccessFeedback.added.length > 0">
                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-emerald-100 dark:bg-emerald-900/60 text-emerald-800 dark:text-emerald-300 font-mono">
                                <strong>+ Added:</strong> <span x-text="htaccessFeedback.added.join(', ')"></span>
                            </span>
                        </template>
                        <template x-if="htaccessFeedback.updated && htaccessFeedback.updated.length > 0">
                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-blue-100 dark:bg-blue-900/60 text-blue-800 dark:text-blue-300 font-mono">
                                <strong>↻ Updated:</strong> <span x-text="htaccessFeedback.updated.join(', ')"></span>
                            </span>
                        </template>
                        <template x-if="htaccessFeedback.removed && htaccessFeedback.removed.length > 0">
                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-rose-100 dark:bg-rose-900/60 text-rose-800 dark:text-rose-300 font-mono">
                                <strong>− Removed:</strong> <span x-text="htaccessFeedback.removed.join(', ')"></span>
                            </span>
                        </template>
                        <template x-if="htaccessFeedback.backup_file">
                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-zinc-200 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 font-mono">
                                <span>Backup:</span> <span x-text="htaccessFeedback.backup_file"></span>
                            </span>
                        </template>
                    </div>

                    <!-- RunCloud Subdomain Aliases Results -->
                    <template x-if="htaccessFeedback.runcloud">
                        <div class="mt-2 pt-2 border-t border-zinc-200/60 dark:border-zinc-800/60 space-y-1">
                            <div class="text-[11px] font-semibold text-zinc-800 dark:text-zinc-200 flex items-center gap-1.5">
                                <span>RunCloud Subdomain Aliases</span>
                                <span class="text-[10px] text-zinc-500 font-normal"
                                      x-text="`(${htaccessFeedback.runcloud.added?.length || 0} added, ${htaccessFeedback.runcloud.skipped?.length || 0} skipped, ${htaccessFeedback.runcloud.failed?.length || 0} failed)`"></span>
                            </div>

                            <div class="flex items-center flex-wrap gap-1.5 text-[11px]">
                                <!-- Added in RunCloud -->
                                <template x-for="item in (htaccessFeedback.runcloud.added || [])" :key="item.subdomain">
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-emerald-100 dark:bg-emerald-900/60 text-emerald-800 dark:text-emerald-300 font-mono">
                                        <strong>+ RunCloud:</strong> <span x-text="item.subdomain"></span>
                                    </span>
                                </template>

                                <!-- Skipped in RunCloud -->
                                <template x-for="item in (htaccessFeedback.runcloud.skipped || [])" :key="item.subdomain">
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-amber-100 dark:bg-amber-900/60 text-amber-800 dark:text-amber-300 font-mono"
                                          :title="item.reason">
                                        <strong>↷ Skipped:</strong> <span x-text="item.subdomain"></span>
                                        <span class="text-[9px] opacity-75" x-text="`(${item.reason})`"></span>
                                    </span>
                                </template>

                                <!-- Failed in RunCloud -->
                                <template x-for="item in (htaccessFeedback.runcloud.failed || [])" :key="item.subdomain">
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-rose-100 dark:bg-rose-900/60 text-rose-800 dark:text-rose-300 font-mono"
                                          :title="item.error">
                                        <strong>✕ Failed:</strong> <span x-text="item.subdomain"></span>
                                        <span class="text-[9px] font-sans text-rose-700 dark:text-rose-400" x-text="`— ${item.error}`"></span>
                                    </span>
                                </template>
                            </div>
                        </div>
                    </template>

                    <!-- Optional notice if no </IfModule> existed -->
                    <template x-if="htaccessFeedback.notice">
                        <p class="text-[11px] text-amber-700 dark:text-amber-400 font-medium mt-1"
                           x-text="htaccessFeedback.notice"></p>
                    </template>
                </div>

                <button type="button"
                        @click="htaccessFeedback = null"
                        class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 p-0.5 rounded">
                    <x-lucide-x class="size-3.5" />
                </button>
            </div>
        </div>
    </template>

    <!-- Detected Subfolders Checkbox List -->
    <div class="p-3 space-y-2 max-h-56 overflow-y-auto">
        <template x-if="isLoadingSubdomainFolders && subdomainFolders.length === 0">
            <div class="py-6 flex items-center justify-center text-zinc-400">
                <span class="animate-spin inline-block size-4 border-2 border-zinc-300 border-t-zinc-800 rounded-full"></span>
            </div>
        </template>

        <template x-for="folder in subdomainFolders" :key="folder.id">
            <label class="flex items-center justify-between gap-3 rounded-md border px-3 py-2.5 transition cursor-pointer select-none"
                   :class="isFolderSelected(folder)
                       ? 'border-indigo-300 dark:border-indigo-800 bg-indigo-50/50 dark:bg-indigo-950/20'
                       : 'border-zinc-200 dark:border-zinc-800 hover:border-zinc-300 dark:hover:border-zinc-700 bg-zinc-50/40 dark:bg-zinc-900/30'">
                <div class="flex items-center gap-3 min-w-0">
                    <input type="checkbox"
                           :checked="isFolderSelected(folder)"
                           @change="toggleFolder(folder)"
                           class="size-4 rounded border-zinc-300 dark:border-zinc-700 text-indigo-600 focus:ring-indigo-500 transition">

                    <div class="flex items-center gap-2 flex-wrap min-w-0">
                        <span class="text-[10px] px-1.5 py-0.5 rounded font-medium uppercase tracking-wider select-none bg-amber-100 dark:bg-amber-950/80 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-900">
                            Folder
                        </span>
                        <span class="font-mono text-[12px] font-medium text-zinc-900 dark:text-zinc-100" x-text="folder.name"></span>
                        <span class="text-xs text-zinc-400">→</span>
                        <span class="font-mono text-[12px] font-medium text-indigo-600 dark:text-indigo-400"
                              x-text="`${getSubdomainLabel(folder.name)}.${deployment?.domain || ''}`"></span>
                    </div>
                </div>

                <div class="shrink-0 flex items-center gap-1.5">
                    <span x-show="isConfiguredInHtaccess(folder)"
                          class="text-[10px] px-1.5 py-0.5 rounded font-medium bg-emerald-100 dark:bg-emerald-950/80 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-900 inline-flex items-center gap-1">
                        <x-lucide-check class="size-2.5" />
                        <span>Active in .htaccess</span>
                    </span>
                </div>
            </label>
        </template>
    </div>

    <!-- Pagination & Selection Summary Footer -->
    <div class="flex flex-col sm:flex-row items-center justify-between gap-3 px-4 py-2.5 border-t border-zinc-200 dark:border-zinc-800 text-xs text-zinc-600 dark:text-zinc-400"
         x-show="subdomainFoldersMeta.total > 0">
        <div class="flex items-center gap-2">
            <span>
                Showing
                <span class="font-medium text-zinc-900 dark:text-zinc-100" x-text="subdomainFoldersFrom"></span>
                to
                <span class="font-medium text-zinc-900 dark:text-zinc-100" x-text="subdomainFoldersTo"></span>
                of
                <span class="font-medium text-zinc-900 dark:text-zinc-100" x-text="subdomainFoldersMeta.total"></span>
            </span>
            <span class="text-zinc-300 dark:text-zinc-700">•</span>
            <span :class="selectedSubdomainFolders.length > 0 ? 'text-indigo-600 dark:text-indigo-400 font-semibold' : 'text-zinc-400'"
                  x-text="`${selectedSubdomainFolders.length} checked for routing`"></span>
        </div>

        <div class="flex items-center gap-1.5" x-show="subdomainFoldersMeta.last_page > 1">
            <button type="button"
                    @click="goToSubdomainFoldersPage(subdomainFoldersMeta.current_page - 1)"
                    :disabled="subdomainFoldersMeta.current_page <= 1 || isLoadingSubdomainFolders"
                    :class="subdomainFoldersMeta.current_page <= 1 || isLoadingSubdomainFolders
                        ? 'opacity-40 cursor-not-allowed text-zinc-400 dark:text-zinc-600'
                        : 'hover:bg-zinc-100 dark:hover:bg-zinc-800 text-zinc-700 dark:text-zinc-300 cursor-pointer'"
                    class="px-2.5 py-1 rounded border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-[#18181b] inline-flex items-center gap-1 transition">
                <x-lucide-chevron-left class="size-3.5" />
                <span>Prev</span>
            </button>

            <span class="px-2 text-zinc-500 dark:text-zinc-400"
                  x-text="`Page ${subdomainFoldersMeta.current_page} of ${subdomainFoldersMeta.last_page}`"></span>

            <button type="button"
                    @click="goToSubdomainFoldersPage(subdomainFoldersMeta.current_page + 1)"
                    :disabled="subdomainFoldersMeta.current_page >= subdomainFoldersMeta.last_page || isLoadingSubdomainFolders"
                    :class="subdomainFoldersMeta.current_page >= subdomainFoldersMeta.last_page || isLoadingSubdomainFolders
                        ? 'opacity-40 cursor-not-allowed text-zinc-400 dark:text-zinc-600'
                        : 'hover:bg-zinc-100 dark:hover:bg-zinc-800 text-zinc-700 dark:text-zinc-300 cursor-pointer'"
                    class="px-2.5 py-1 rounded border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-[#18181b] inline-flex items-center gap-1 transition">
                <span>Next</span>
                <x-lucide-chevron-right class="size-3.5" />
            </button>
        </div>
    </div>

    <!-- Rule Preview Modal -->
    <div x-show="showHtaccessPreview"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         aria-labelledby="modal-title"
         role="dialog"
         aria-modal="true">

        <!-- Backdrop -->
        <div x-show="showHtaccessPreview"
             x-transition:enter="ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-zinc-950/60 dark:bg-zinc-950/80 backdrop-blur-xs"
             @click="showHtaccessPreview = false"
             aria-hidden="true"></div>

        <!-- Modal Panel -->
        <div x-show="showHtaccessPreview"
             x-transition:enter="ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="relative w-full max-w-2xl max-h-[90vh] flex flex-col bg-white dark:bg-[#18181b] border border-zinc-200 dark:border-zinc-800 shadow-xl rounded-xl overflow-hidden">

            <!-- Modal Header (sticky) -->
            <div class="flex items-center justify-between px-5 py-4 border-b border-zinc-200 dark:border-zinc-800 shrink-0">
                <div>
                    <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100" id="modal-title">
                        .htaccess Subdomain Routing Preview
                    </h3>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                        Domain: <span class="font-mono font-medium text-zinc-700 dark:text-zinc-300" x-text="deployment?.domain"></span>
                        <span class="mx-1 text-zinc-300 dark:text-zinc-700">•</span>
                        Insertion: <span class="font-medium" x-text="htaccessPreviewData?.has_if_module ? 'After first </IfModule>' : 'Appended to end of file'"></span>
                    </p>
                </div>
                <button type="button"
                        @click="showHtaccessPreview = false"
                        class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 p-1 rounded-md shrink-0 ml-3">
                    <x-lucide-x class="size-4" />
                </button>
            </div>

            <!-- Modal Body (scrollable) -->
            <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4">

                <!-- Changes Summary Pills -->
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500 mb-2">Changes</p>
                    <div class="flex items-center flex-wrap gap-2 text-xs min-h-[24px]">
                        <template x-if="htaccessPreviewData?.added && htaccessPreviewData.added.length > 0">
                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 font-mono">
                                <strong>+ Add:</strong> <span x-text="htaccessPreviewData.added.join(', ')"></span>
                            </span>
                        </template>
                        <template x-if="htaccessPreviewData?.updated && htaccessPreviewData.updated.length > 0">
                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-blue-100 dark:bg-blue-950 text-blue-800 dark:text-blue-300 border border-blue-200 dark:border-blue-800 font-mono">
                                <strong>↻ Update:</strong> <span x-text="htaccessPreviewData.updated.join(', ')"></span>
                            </span>
                        </template>
                        <template x-if="htaccessPreviewData?.removed && htaccessPreviewData.removed.length > 0">
                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-rose-100 dark:bg-rose-950 text-rose-800 dark:text-rose-300 border border-rose-200 dark:border-rose-800 font-mono">
                                <strong>− Remove:</strong> <span x-text="htaccessPreviewData.removed.join(', ')"></span>
                            </span>
                        </template>
                        <template x-if="(!htaccessPreviewData?.added || htaccessPreviewData.added.length === 0) && (!htaccessPreviewData?.updated || htaccessPreviewData.updated.length === 0) && (!htaccessPreviewData?.removed || htaccessPreviewData.removed.length === 0)">
                            <span class="text-zinc-400 dark:text-zinc-500 italic">No changes detected</span>
                        </template>
                    </div>
                </div>

                <!-- Optional Notice -->
                <template x-if="htaccessPreviewData?.notice">
                    <div class="px-3 py-2 rounded-md bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-900 text-xs text-amber-800 dark:text-amber-200 flex items-center gap-2">
                        <x-lucide-alert-triangle class="size-4 shrink-0" />
                        <span x-text="htaccessPreviewData.notice"></span>
                    </div>
                </template>

                <!-- Code block -->
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500 mb-2">Generated Rules</p>
                    <div class="rounded-lg overflow-hidden border border-zinc-200 dark:border-zinc-800 bg-zinc-950 text-zinc-100">
                        <pre class="p-4 text-[11px] font-mono leading-relaxed overflow-x-auto max-h-56" x-text="htaccessPreviewData?.rules || '# No rules to generate'"></pre>
                    </div>
                </div>

                <!-- RunCloud Subdomain Aliases -->
                <div class="rounded-lg border border-zinc-200 dark:border-zinc-800 bg-zinc-50/60 dark:bg-zinc-900/40 p-3">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs font-semibold text-zinc-900 dark:text-zinc-100">RunCloud Subdomain Aliases</span>
                        <span class="text-[10px] text-zinc-500 dark:text-zinc-400 bg-zinc-200 dark:bg-zinc-800 px-1.5 py-0.5 rounded font-medium">Cloudflare Proxied • DNS Integration</span>
                    </div>
                    <p class="text-[11px] text-zinc-500 dark:text-zinc-400 mb-2">
                        Will be added as aliases in RunCloud after .htaccess is applied:
                    </p>
                    <div class="flex items-center flex-wrap gap-1.5">
                        <template x-for="label in selectedSubdomainFolders" :key="label">
                            <span class="px-2 py-0.5 rounded bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-900 font-mono text-[11px]"
                                  x-text="`${label}.${deployment?.domain}`"></span>
                        </template>
                    </div>
                </div>

            </div>

            <!-- Modal Footer (sticky) -->
            <div class="flex items-center justify-end gap-2.5 px-5 py-3 border-t border-zinc-200 dark:border-zinc-800 bg-zinc-50/60 dark:bg-zinc-900/40 shrink-0">
                <button type="button"
                        @click="showHtaccessPreview = false"
                        class="px-3 py-1.5 text-xs font-medium rounded-md border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-800 transition">
                    Close
                </button>
                <button type="button"
                        @click="showHtaccessPreview = false; applyHtaccessRules()"
                        :disabled="isApplyingHtaccess"
                        class="px-3.5 py-1.5 text-xs font-semibold rounded-md bg-zinc-900 hover:bg-zinc-800 text-white dark:bg-zinc-100 dark:hover:bg-white dark:text-zinc-900 shadow-sm transition inline-flex items-center gap-1.5 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                    <template x-if="isApplyingHtaccess">
                        <span class="animate-spin inline-block size-3 border-2 border-white/30 border-t-white dark:border-zinc-900/30 dark:border-t-zinc-900 rounded-full"></span>
                    </template>
                    <template x-if="!isApplyingHtaccess">
                        <x-lucide-check class="size-3.5" />
                    </template>
                    <span x-text="isApplyingHtaccess ? 'Applying...' : 'Apply to .htaccess'"></span>
                </button>
            </div>
        </div>
    </div>
</div>
