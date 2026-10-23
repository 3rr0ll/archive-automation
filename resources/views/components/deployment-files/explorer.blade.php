<!-- Left: Activity / Sidebar (Explorer) -->
<aside class="w-64 sm:w-72 border-r flex flex-col shrink-0 overflow-hidden"
       :class="theme === 'dark' ? 'bg-[#18181b] border-zinc-800' : 'bg-zinc-50/70 border-zinc-200'">
    
    <!-- Sidebar Header / Title -->
    <div class="h-8 px-3 border-b flex items-center justify-between uppercase tracking-wider text-[11px] font-semibold select-none"
         :class="theme === 'dark' ? 'text-zinc-400 border-zinc-800 bg-[#18181b]' : 'text-zinc-500 border-zinc-200 bg-zinc-100/50'">
        <div class="flex items-center gap-1.5">
            <x-lucide-folder-tree class="size-3.5" />
            <span>Explorer</span>
        </div>
        
        <div class="flex items-center gap-0.5">
            <!-- New File Button -->
            <button type="button" 
                    @click="startNewFile()" 
                    class="p-1 rounded transition cursor-pointer"
                    :class="theme === 'dark' ? 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800' : 'text-zinc-500 hover:text-zinc-900 hover:bg-zinc-200/60'"
                    title="New File">
                <x-lucide-file-plus class="size-3.5" />
            </button>
            <!-- Refresh Button -->
            <button type="button" 
                    @click="loadTree()" 
                    class="p-1 rounded transition cursor-pointer"
                    :class="theme === 'dark' ? 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800' : 'text-zinc-500 hover:text-zinc-900 hover:bg-zinc-200/60'"
                    title="Refresh Explorer">
                <x-lucide-rotate-cw class="size-3.5" x-bind:class="treeLoading ? 'animate-spin' : ''" />
            </button>
        </div>
    </div>

    <!-- Path Breadcrumb inside Sidebar -->
    <div class="px-3 py-1 border-b text-[11px] font-mono truncate select-none flex items-center justify-between"
         :class="theme === 'dark' ? 'text-zinc-500 border-zinc-800/80 bg-zinc-900/40' : 'text-zinc-400 border-zinc-200/80 bg-zinc-100/30'">
        <span>public_html</span>
        <span x-show="searchFileQuery" class="text-[10px] text-indigo-400 font-sans" x-text="`${visibleTreeItems.length} found`"></span>
    </div>

    <!-- Explorer File Search Input -->
    <div class="p-1.5 border-b"
         :class="theme === 'dark' ? 'border-zinc-800 bg-zinc-900/30' : 'border-zinc-200 bg-zinc-50/50'">
        <div class="relative flex items-center">
            <x-lucide-search class="size-3 text-zinc-400 absolute left-2 pointer-events-none" />
            <input type="text"
                   x-model="searchFileQuery"
                   placeholder="Filter files (name)..."
                   class="w-full bg-transparent pl-6 pr-6 py-0.5 text-[11px] font-mono border rounded outline-none transition"
                   :class="theme === 'dark' ? 'border-zinc-800 text-zinc-200 placeholder:text-zinc-600 focus:border-zinc-700 focus:bg-zinc-900' : 'border-zinc-200 text-zinc-800 placeholder:text-zinc-400 focus:border-zinc-300 focus:bg-white'">
            <button type="button" 
                    x-show="searchFileQuery" 
                    @click="searchFileQuery = ''"
                    class="absolute right-1.5 p-0.5 text-zinc-400 hover:text-zinc-200 rounded">
                <x-lucide-x class="size-2.5" />
            </button>
        </div>
    </div>

    <!-- Inline New File Input Form -->
    <div x-show="isCreatingFile" 
         x-cloak
         class="p-1.5 border-b"
         :class="theme === 'dark' ? 'border-zinc-800 bg-zinc-900/70' : 'border-zinc-200 bg-zinc-50'">
        <div class="flex items-center gap-1.5">
            <x-lucide-file class="size-3.5 text-zinc-400 shrink-0" />
            <input id="new-file-name-input"
                   type="text" 
                   x-model="newFileName"
                   @keydown.enter.prevent="submitNewFile()"
                   @keydown.escape.prevent="cancelNewFile()"
                   placeholder="filename.html" 
                   class="flex-1 min-w-0 bg-transparent text-xs font-mono px-1 py-0.5 border rounded outline-none"
                   :class="theme === 'dark' ? 'border-zinc-700 text-zinc-200 focus:border-indigo-500' : 'border-zinc-300 text-zinc-800 focus:border-indigo-600'">
        </div>
        <div class="flex items-center justify-end gap-1 mt-1 text-[10px] text-zinc-400 font-mono">
            <span>Press <kbd>Enter</kbd> to create, <kbd>Esc</kbd> to cancel</span>
        </div>
    </div>

    <!-- Explorer File List (Tree Accordion) -->
    <div class="flex-1 overflow-y-auto p-1 font-mono text-[12px] select-none">
        <!-- Loading State (Skeleton File Tree) -->
        <template x-if="treeLoading">
            <div class="p-2 space-y-2 animate-pulse select-none" aria-hidden="true">
                <!-- Status indicator -->
                <div class="px-2 py-1.5 flex items-center gap-2 text-xs"
                     :class="theme === 'dark' ? 'text-zinc-400' : 'text-zinc-500'">
                    <span class="animate-spin inline-block size-3.5 border-2 rounded-full shrink-0"
                          :class="theme === 'dark' ? 'border-zinc-600 border-t-zinc-200' : 'border-zinc-300 border-t-zinc-800'"></span>
                    <span class="font-sans text-[11px] font-medium tracking-wide">Loading workspace files...</span>
                </div>

                <!-- Skeleton Rows Mocking Folders and Files -->
                <div class="space-y-1.5 pt-1">
                    <!-- Folder 1 -->
                    <div class="flex items-center gap-2 px-2 py-1.5 rounded"
                         :class="theme === 'dark' ? 'bg-zinc-800/40' : 'bg-zinc-100/70'">
                        <div class="size-3.5 rounded shrink-0" :class="theme === 'dark' ? 'bg-zinc-700/80' : 'bg-zinc-300'"></div>
                        <div class="h-3 w-20 rounded" :class="theme === 'dark' ? 'bg-zinc-700/80' : 'bg-zinc-300'"></div>
                    </div>
                    <!-- Nested items -->
                    <div class="pl-5 space-y-1.5">
                        <div class="flex items-center justify-between px-2 py-1 rounded"
                             :class="theme === 'dark' ? 'bg-zinc-800/20' : 'bg-zinc-100/50'">
                            <div class="flex items-center gap-2">
                                <div class="size-3.5 rounded shrink-0" :class="theme === 'dark' ? 'bg-zinc-700/60' : 'bg-zinc-300/80'"></div>
                                <div class="h-3 w-24 rounded" :class="theme === 'dark' ? 'bg-zinc-700/60' : 'bg-zinc-300/80'"></div>
                            </div>
                            <div class="h-2.5 w-7 rounded" :class="theme === 'dark' ? 'bg-zinc-800' : 'bg-zinc-200'"></div>
                        </div>
                        <div class="flex items-center justify-between px-2 py-1 rounded"
                             :class="theme === 'dark' ? 'bg-zinc-800/20' : 'bg-zinc-100/50'">
                            <div class="flex items-center gap-2">
                                <div class="size-3.5 rounded shrink-0" :class="theme === 'dark' ? 'bg-zinc-700/60' : 'bg-zinc-300/80'"></div>
                                <div class="h-3 w-28 rounded" :class="theme === 'dark' ? 'bg-zinc-700/60' : 'bg-zinc-300/80'"></div>
                            </div>
                            <div class="h-2.5 w-8 rounded" :class="theme === 'dark' ? 'bg-zinc-800' : 'bg-zinc-200'"></div>
                        </div>
                    </div>

                    <!-- Folder 2 -->
                    <div class="flex items-center gap-2 px-2 py-1.5 rounded"
                         :class="theme === 'dark' ? 'bg-zinc-800/40' : 'bg-zinc-100/70'">
                        <div class="size-3.5 rounded shrink-0" :class="theme === 'dark' ? 'bg-zinc-700/80' : 'bg-zinc-300'"></div>
                        <div class="h-3 w-16 rounded" :class="theme === 'dark' ? 'bg-zinc-700/80' : 'bg-zinc-300'"></div>
                    </div>
                    <!-- Nested item -->
                    <div class="pl-5 space-y-1.5">
                        <div class="flex items-center justify-between px-2 py-1 rounded"
                             :class="theme === 'dark' ? 'bg-zinc-800/20' : 'bg-zinc-100/50'">
                            <div class="flex items-center gap-2">
                                <div class="size-3.5 rounded shrink-0" :class="theme === 'dark' ? 'bg-zinc-700/60' : 'bg-zinc-300/80'"></div>
                                <div class="h-3 w-20 rounded" :class="theme === 'dark' ? 'bg-zinc-700/60' : 'bg-zinc-300/80'"></div>
                            </div>
                            <div class="h-2.5 w-6 rounded" :class="theme === 'dark' ? 'bg-zinc-800' : 'bg-zinc-200'"></div>
                        </div>
                    </div>

                    <!-- Root Files -->
                    <div class="flex items-center justify-between px-2 py-1 rounded"
                         :class="theme === 'dark' ? 'bg-zinc-800/20' : 'bg-zinc-100/50'">
                        <div class="flex items-center gap-2">
                            <div class="size-3.5 rounded shrink-0" :class="theme === 'dark' ? 'bg-zinc-700/60' : 'bg-zinc-300/80'"></div>
                            <div class="h-3 w-24 rounded" :class="theme === 'dark' ? 'bg-zinc-700/60' : 'bg-zinc-300/80'"></div>
                        </div>
                        <div class="h-2.5 w-8 rounded" :class="theme === 'dark' ? 'bg-zinc-800' : 'bg-zinc-200'"></div>
                    </div>
                    <div class="flex items-center justify-between px-2 py-1 rounded"
                         :class="theme === 'dark' ? 'bg-zinc-800/20' : 'bg-zinc-100/50'">
                        <div class="flex items-center gap-2">
                            <div class="size-3.5 rounded shrink-0" :class="theme === 'dark' ? 'bg-zinc-700/60' : 'bg-zinc-300/80'"></div>
                            <div class="h-3 w-32 rounded" :class="theme === 'dark' ? 'bg-zinc-700/60' : 'bg-zinc-300/80'"></div>
                        </div>
                        <div class="h-2.5 w-7 rounded" :class="theme === 'dark' ? 'bg-zinc-800' : 'bg-zinc-200'"></div>
                    </div>
                    <div class="flex items-center justify-between px-2 py-1 rounded"
                         :class="theme === 'dark' ? 'bg-zinc-800/20' : 'bg-zinc-100/50'">
                        <div class="flex items-center gap-2">
                            <div class="size-3.5 rounded shrink-0" :class="theme === 'dark' ? 'bg-zinc-700/60' : 'bg-zinc-300/80'"></div>
                            <div class="h-3 w-16 rounded" :class="theme === 'dark' ? 'bg-zinc-700/60' : 'bg-zinc-300/80'"></div>
                        </div>
                        <div class="h-2.5 w-6 rounded" :class="theme === 'dark' ? 'bg-zinc-800' : 'bg-zinc-200'"></div>
                    </div>
                </div>
            </div>
        </template>

        <!-- Empty State -->
        <template x-if="!treeLoading && visibleTreeItems.length === 0">
            <div class="p-4 text-center text-xs font-sans" :class="theme === 'dark' ? 'text-zinc-500' : 'text-zinc-400'">
                No files in directory.
            </div>
        </template>

        <!-- Tree Items Loop -->
        <template x-for="item in visibleTreeItems" :key="item.path">
            <button type="button"
                    @click="selectItem(item)"
                    class="w-full flex items-center justify-between py-1 pr-1.5 rounded text-left transition cursor-pointer group"
                    :style="`padding-left: ${Math.max(4, (item.level || 0) * 14 + 4)}px;`"
                    :class="{
                        'bg-[#094771] text-white': theme === 'dark' && activeFile && activeFile.path === item.path,
                        'bg-zinc-200 text-zinc-900 font-semibold': theme === 'light' && activeFile && activeFile.path === item.path,
                        'text-zinc-400 hover:bg-zinc-800/60 hover:text-zinc-200': theme === 'dark' && (!activeFile || activeFile.path !== item.path),
                        'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900': theme === 'light' && (!activeFile || activeFile.path !== item.path)
                    }">
                <div class="flex items-center gap-1.5 min-w-0 truncate">
                    <!-- Folder Chevron Arrow -->
                    <template x-if="item.type === 'directory'">
                        <span class="shrink-0 text-zinc-400 transition-transform duration-100" :class="item.expanded ? 'rotate-90' : ''">
                            <template x-if="item.loading">
                                <span class="animate-spin inline-block size-3 border border-zinc-400 border-t-transparent rounded-full"></span>
                            </template>
                            <template x-if="!item.loading">
                                <x-lucide-chevron-right class="size-3" />
                            </template>
                        </span>
                    </template>

                    <!-- Folder Open / Closed Icon -->
                    <template x-if="item.type === 'directory'">
                        <template x-if="item.expanded">
                            <x-lucide-folder-open class="size-3.5 text-amber-500 shrink-0" />
                        </template>
                        <template x-if="!item.expanded">
                            <x-lucide-folder class="size-3.5 text-amber-500 shrink-0" />
                        </template>
                    </template>

                    <!-- File Icons -->
                    <template x-if="item.type === 'file' && (item.name.endsWith('.html') || item.name.endsWith('.htm'))">
                        <x-lucide-code-2 class="size-3.5 text-orange-500 shrink-0" />
                    </template>
                    <template x-if="item.type === 'file' && item.name.endsWith('.css')">
                        <x-lucide-palette class="size-3.5 text-sky-400 shrink-0" />
                    </template>
                    <template x-if="item.type === 'file' && (item.name.endsWith('.js') || item.name.endsWith('.json'))">
                        <x-lucide-file-json class="size-3.5 text-yellow-400 shrink-0" />
                    </template>
                    <template x-if="item.type === 'file' && item.name.endsWith('.php')">
                        <x-lucide-file-code class="size-3.5 text-indigo-400 shrink-0" />
                    </template>
                    <template x-if="item.type === 'file' && !item.name.endsWith('.html') && !item.name.endsWith('.htm') && !item.name.endsWith('.css') && !item.name.endsWith('.js') && !item.name.endsWith('.json') && !item.name.endsWith('.php')">
                        <x-lucide-file-text class="size-3.5 text-zinc-400 shrink-0" />
                    </template>

                    <span class="truncate text-[12px]" x-text="item.name"></span>
                </div>
                <span class="text-[10px] opacity-60 font-sans shrink-0 pl-1" 
                      x-show="item.type === 'file'"
                      x-text="formatSize(item.size)"></span>
            </button>
        </template>
    </div>
</aside>
