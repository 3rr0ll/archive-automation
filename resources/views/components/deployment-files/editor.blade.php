<!-- Right: Code Editor & Tab Area -->
<main class="flex-1 min-w-0 flex flex-col relative overflow-hidden"
      :class="theme === 'dark' ? 'bg-[#1e1e1e]' : 'bg-white'">
    
    <!-- Editor Tabs Bar (IDE Tab header) -->
    <div class="h-8 border-b flex items-center justify-between shrink-0 select-none overflow-x-auto"
         :class="theme === 'dark' ? 'bg-[#18181b] border-zinc-800' : 'bg-zinc-100/60 border-zinc-200'">
        
        <!-- Open Tabs List -->
        <div class="flex items-center h-full min-w-0 overflow-x-auto">
            <template x-for="tab in openTabs" :key="tab.path">
                <div @click="switchToTab(tab)"
                     class="h-full px-3 flex items-center gap-2 border-r text-xs font-mono font-medium transition cursor-pointer group shrink-0"
                     :class="{
                         'bg-[#1e1e1e] border-t-2 border-t-indigo-500 border-r-zinc-800 text-zinc-100': theme === 'dark' && activeFile && activeFile.path === tab.path,
                         'bg-white border-t-2 border-t-indigo-600 border-r-zinc-200 text-zinc-900': theme === 'light' && activeFile && activeFile.path === tab.path,
                         'border-t-2 border-t-transparent border-r-zinc-800/60 text-zinc-400 hover:bg-zinc-800/40 hover:text-zinc-200': theme === 'dark' && (!activeFile || activeFile.path !== tab.path),
                         'border-t-2 border-t-transparent border-r-zinc-200/60 text-zinc-600 hover:bg-zinc-200/50 hover:text-zinc-900': theme === 'light' && (!activeFile || activeFile.path !== tab.path)
                     }">
                    <!-- File Icon -->
                    <template x-if="tab.name.endsWith('.html') || tab.name.endsWith('.htm')">
                        <x-lucide-code-2 class="size-3.5 text-orange-500 shrink-0" />
                    </template>
                    <template x-if="tab.name.endsWith('.css')">
                        <x-lucide-palette class="size-3.5 text-sky-400 shrink-0" />
                    </template>
                    <template x-if="tab.name.endsWith('.js') || tab.name.endsWith('.json')">
                        <x-lucide-file-json class="size-3.5 text-yellow-400 shrink-0" />
                    </template>
                    <template x-if="tab.name.endsWith('.php')">
                        <x-lucide-file-code class="size-3.5 text-indigo-400 shrink-0" />
                    </template>
                    <template x-if="!tab.name.endsWith('.html') && !tab.name.endsWith('.htm') && !tab.name.endsWith('.css') && !tab.name.endsWith('.js') && !tab.name.endsWith('.json') && !tab.name.endsWith('.php')">
                        <x-lucide-file-text class="size-3.5 text-zinc-400 shrink-0" />
                    </template>
                    
                    <span class="truncate max-w-[140px]" x-text="tab.name"></span>
                    
                    <!-- Unsaved Dot or Close Tab button -->
                    <button type="button" 
                            @click.stop="closeTab(tab, $event)"
                            class="p-0.5 rounded hover:bg-zinc-500/20 text-zinc-400 hover:text-zinc-100 transition"
                            title="Close">
                        <template x-if="tab.isModified">
                            <span class="size-1.5 block rounded-full bg-zinc-900 dark:bg-zinc-100 group-hover:hidden"></span>
                        </template>
                        <span :class="tab.isModified ? 'hidden group-hover:block' : 'block'">
                            <x-lucide-x class="size-3" />
                        </span>
                    </button>
                </div>
            </template>

            <template x-if="openTabs.length === 0">
                <div class="px-3 text-xs text-zinc-400 font-mono">
                    No files open
                </div>
            </template>
        </div>

        <!-- Breadcrumb Path in Tab Bar -->
        <div class="px-3 text-[11px] font-mono truncate hidden sm:block shrink-0"
             :class="theme === 'dark' ? 'text-zinc-500' : 'text-zinc-400'"
             x-show="activeFile"
             x-text="activeFile ? activeFile.path.replace(rootDir, 'public_html') : ''">
        </div>
    </div>

    <!-- Editor Loading Overlay -->
    <div x-show="fileLoading" 
         class="absolute inset-0 z-20 flex flex-col items-center justify-center gap-2"
         :class="theme === 'dark' ? 'bg-[#1e1e1e]/80 text-white' : 'bg-white/80 text-zinc-800'">
        <span class="animate-spin inline-block size-5 border-2 rounded-full"
              :class="theme === 'dark' ? 'border-zinc-600 border-t-zinc-200' : 'border-zinc-300 border-t-zinc-800'"></span>
        <span class="text-xs font-mono">Opening file...</span>
    </div>

    <!-- Empty Selection Placeholder -->
    <div x-show="!activeFile && !fileLoading" 
         class="flex-1 flex flex-col items-center justify-center p-8 text-center"
         :class="theme === 'dark' ? 'text-zinc-500' : 'text-zinc-400'">
        <div :class="theme === 'dark' ? 'text-zinc-700' : 'text-zinc-300'">
            <x-lucide-code-2 class="size-10 mb-2 stroke-[1.25]" />
        </div>
        <h4 class="text-xs font-mono font-semibold uppercase tracking-wider mb-1" :class="theme === 'dark' ? 'text-zinc-400' : 'text-zinc-600'">No File Opened</h4>
        <p class="text-xs font-mono max-w-xs">Select a file from the explorer sidebar or create a new one to edit.</p>
    </div>

    <!-- CodeMirror Mounting Node -->
    <div id="codemirror-editor" 
         x-show="activeFile" 
         class="flex-1 min-h-0 w-full overflow-hidden text-left">
    </div>
</main>
