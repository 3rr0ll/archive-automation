<!-- Right: Code Editor & Tab Area -->
<main class="flex-1 min-w-0 flex flex-col relative overflow-hidden"
      :class="theme === 'dark' ? 'bg-[#1e1e22]' : 'bg-white'">
    
    <!-- Editor Tabs Bar (IDE Tab header) -->
    <div class="h-8 border-b flex items-center justify-between shrink-0 select-none overflow-x-auto"
         :class="theme === 'dark' ? 'bg-[#18181b] border-zinc-800' : 'bg-zinc-100/60 border-zinc-200'">
        
        <!-- Open Tabs List -->
        <div class="flex items-center h-full min-w-0 overflow-x-auto">
            <template x-for="tab in openTabs" :key="tab.path">
                <div @click="switchToTab(tab)"
                     class="h-full px-3 flex items-center gap-2 border-r text-xs font-mono font-medium transition cursor-pointer group shrink-0"
                     :class="{
                         'bg-[#1e1e22] border-t-2 border-t-indigo-500 border-r-zinc-800 text-zinc-100': theme === 'dark' && activeFile && activeFile.path === tab.path,
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

    <!-- CodeMirror Mounting Node (Isolated with x-ignore to prevent Alpine DOM observation/proxying) -->
    <div id="codemirror-editor" 
         x-ignore
         x-show="activeFile" 
         class="flex-1 min-h-0 w-full overflow-hidden text-left relative">
    </div>

    <!-- Top-Right Floating Find & Replace Widget (VS Code / JetBrains Style) -->
    <div x-show="searchOpen && activeFile"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 -translate-y-2 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 -translate-y-2 scale-95"
         @keydown.escape.prevent="closeEditorSearch()"
         class="absolute top-10 right-4 z-30 flex flex-col p-1.5 rounded-lg border shadow-2xl backdrop-blur-md select-none transition-all"
         :class="theme === 'dark' ? 'bg-[#1e1e22]/95 border-zinc-700/80 text-zinc-200 shadow-black/60' : 'bg-white/95 border-zinc-300 text-zinc-800 shadow-zinc-400/30'">
        
         
        <!-- Search Row -->
        <div class="flex items-center gap-1.5">
            <!-- Toggle Replace Arrow (caret) -->
            <button type="button"
                    @click="toggleReplace()"
                    class="p-1 rounded hover:bg-zinc-700/40 text-zinc-400 hover:text-zinc-200 transition"
                    :title="replaceOpen ? 'Collapse Replace' : 'Expand Replace'">
                <span class="inline-flex transition-transform duration-150" :class="replaceOpen ? 'rotate-90 text-indigo-400' : ''">
                    <x-lucide-chevron-right class="size-3.5" />
                </span>
            </button>

            <!-- Search Input Box with embedded toggles & count -->
            <div class="flex items-center h-7 px-2 rounded border focus-within:ring-1 focus-within:ring-indigo-500 transition"
                 :class="theme === 'dark' ? 'bg-[#28282d] border-zinc-700/80' : 'bg-zinc-100 border-zinc-300'">
                <input id="editor-search-input"
                       type="text"
                       x-model="searchQuery"
                       @input="updateSearchQuery()"
                       @keydown.enter.prevent="if ($event.shiftKey) { findPrevMatch(); } else { findNextMatch(); }"
                       placeholder="Find"
                       autocomplete="off"
                       spellcheck="false"
                       class="w-44 sm:w-52 bg-transparent text-xs font-mono outline-none border-none p-0 focus:ring-0 placeholder-zinc-500"
                       :class="theme === 'dark' ? 'text-zinc-100' : 'text-zinc-900'" />

                <!-- In-Input Action Buttons: Aa, ab, .* -->
                <div class="flex items-center gap-0.5 ml-1.5 pl-1.5 border-l"
                     :class="theme === 'dark' ? 'border-zinc-700' : 'border-zinc-300'">
                    <!-- Case Sensitive (Aa) -->
                    <button type="button"
                            @click="toggleCaseSensitive()"
                            class="px-1 py-0.5 rounded text-[10px] font-mono font-bold transition leading-none"
                            :class="caseSensitive 
                                ? 'bg-indigo-600 text-white' 
                                : (theme === 'dark' ? 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-700/50' : 'text-zinc-500 hover:text-zinc-900 hover:bg-zinc-200')"
                            title="Match Case (Alt+C)">
                        Aa
                    </button>

                    <!-- Whole Word (ab with underline) -->
                    <button type="button"
                            @click="toggleWholeWord()"
                            class="px-1 py-0.5 rounded text-[10px] font-mono font-bold underline underline-offset-2 transition leading-none"
                            :class="wholeWord 
                                ? 'bg-indigo-600 text-white' 
                                : (theme === 'dark' ? 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-700/50' : 'text-zinc-500 hover:text-zinc-900 hover:bg-zinc-200')"
                            title="Match Whole Word (Alt+W)">
                        ab
                    </button>

                    <!-- Regular Expression (.*) -->
                    <button type="button"
                            @click="toggleRegex()"
                            class="px-1 py-0.5 rounded text-[10px] font-mono font-bold transition leading-none"
                            :class="isRegex 
                                ? 'bg-indigo-600 text-white' 
                                : (theme === 'dark' ? 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-700/50' : 'text-zinc-500 hover:text-zinc-900 hover:bg-zinc-200')"
                            title="Use Regular Expression (Alt+R)">
                        .*
                    </button>
                </div>
            </div>

            <!-- Match Result Status (e.g. "1 of 5" or "No results") -->
            <div class="text-[11px] font-mono px-1.5 min-w-[70px] text-center"
                 x-show="searchQuery.length > 0">
                <template x-if="matchCount > 0">
                    <span :class="theme === 'dark' ? 'text-zinc-400' : 'text-zinc-600'"
                          x-text="currentMatchIndex > 0 ? (currentMatchIndex + ' of ' + matchCount) : (matchCount + ' match' + (matchCount > 1 ? 'es' : ''))">
                    </span>
                </template>
                <template x-if="matchCount === 0">
                    <span class="text-rose-400 font-medium">No results</span>
                </template>
            </div>

            <!-- Navigation Buttons: Previous (Up Arrow), Next (Down Arrow) -->
            <button type="button"
                    @click="findPrevMatch()"
                    class="p-1 rounded hover:bg-zinc-700/40 text-zinc-400 hover:text-zinc-200 transition"
                    title="Previous Match (Shift+Enter)">
                <x-lucide-arrow-up class="size-3.5" />
            </button>
            <button type="button"
                    @click="findNextMatch()"
                    class="p-1 rounded hover:bg-zinc-700/40 text-zinc-400 hover:text-zinc-200 transition"
                    title="Next Match (Enter)">
                <x-lucide-arrow-down class="size-3.5" />
            </button>

            <!-- Close Widget Button (X) -->
            <button type="button"
                    @click="closeEditorSearch()"
                    class="p-1 rounded hover:bg-zinc-700/40 text-zinc-400 hover:text-zinc-200 transition"
                    title="Close (Esc)">
                <x-lucide-x class="size-3.5" />
            </button>
        </div>

        <!-- Replace Row (Expandable via Chevron or Ctrl+H) -->
        <div x-show="replaceOpen"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 -translate-y-1"
             x-transition:enter-end="opacity-100 translate-y-0"
             class="flex items-center gap-1.5 mt-1.5 pt-1.5 border-t"
             :class="theme === 'dark' ? 'border-zinc-800' : 'border-zinc-200'">
            <!-- Indent spacer for caret alignment -->
            <div class="w-5 shrink-0"></div>

            <!-- Replace Input Box -->
            <div class="flex items-center h-7 px-2 rounded border focus-within:ring-1 focus-within:ring-indigo-500 transition flex-1"
                 :class="theme === 'dark' ? 'bg-[#28282d] border-zinc-700/80' : 'bg-zinc-100 border-zinc-300'">
                <input id="editor-replace-input"
                       type="text"
                       x-model="replaceQuery"
                       @input="updateSearchQuery()"
                       @keydown.enter.prevent="if ($event.altKey) { replaceAllMatches(); } else { replaceCurrent(); }"
                       placeholder="Replace"
                       autocomplete="off"
                       spellcheck="false"
                       class="w-full bg-transparent text-xs font-mono outline-none border-none p-0 focus:ring-0 placeholder-zinc-500"
                       :class="theme === 'dark' ? 'text-zinc-100' : 'text-zinc-900'" />
            </div>

            <!-- Action: Replace 1 -->
            <button type="button"
                    @click="replaceCurrent()"
                    class="px-2 py-1 text-[11px] font-mono rounded font-medium border transition"
                    :class="theme === 'dark' 
                        ? 'border-zinc-700 hover:bg-zinc-800 text-zinc-200' 
                        : 'border-zinc-300 hover:bg-zinc-100 text-zinc-700'"
                    title="Replace (Enter)">
                Replace
            </button>

            <!-- Action: Replace All -->
            <button type="button"
                    @click="replaceAllMatches()"
                    class="px-2 py-1 text-[11px] font-mono rounded font-medium border transition"
                    :class="theme === 'dark' 
                        ? 'border-zinc-700 hover:bg-zinc-800 text-zinc-200' 
                        : 'border-zinc-300 hover:bg-zinc-100 text-zinc-700'"
                    title="Replace All (Alt+Enter)">
                All
            </button>
        </div>
    </div>
</main>
