@props(['deployment'])

<!-- IDE Top Window Bar / Header -->
<div class="h-10 px-3 flex items-center justify-between border-b select-none shrink-0"
     :class="theme === 'dark' ? 'bg-[#18181b] border-zinc-800' : 'bg-zinc-50 border-zinc-200'">
    
    <!-- Left: Project & Breadcrumbs -->
    <div class="flex items-center gap-2 min-w-0">
        <a href="/deployments/{{ $deployment->hash }}/logs" 
           class="inline-flex items-center gap-1.5 text-xs font-medium px-2 py-1 rounded transition"
           :class="theme === 'dark' ? 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800' : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-200/60'">
            <x-lucide-arrow-left class="size-3.5" />
            <span>Logs</span>
        </a>

        <span class="text-zinc-400/40">|</span>

        <!-- Domain / Root Label -->
        <div class="flex items-center gap-1.5 text-xs font-mono min-w-0">
            <x-lucide-folder-git-2 class="size-3.5 text-zinc-400 shrink-0" />
            <span class="font-semibold" :class="theme === 'dark' ? 'text-zinc-200' : 'text-zinc-900'">{{ $deployment->domain }}</span>
            <span class="text-zinc-400 hidden sm:inline" x-show="activeFile" x-text="` › ${activeFile.name}`"></span>
        </div>
    </div>

    <!-- Center: Quick shortcut hints (Desktop) -->
    <div class="hidden md:flex items-center gap-3 text-[11px] text-zinc-400 font-mono">
        <span><kbd class="px-1 py-0.5 rounded text-[10px] font-mono border" :class="theme === 'dark' ? 'bg-zinc-800 border-zinc-700 text-zinc-300' : 'bg-zinc-100 border-zinc-200 text-zinc-600'">Ctrl</kbd> + <kbd class="px-1 py-0.5 rounded text-[10px] font-mono border" :class="theme === 'dark' ? 'bg-zinc-800 border-zinc-700 text-zinc-300' : 'bg-zinc-100 border-zinc-200 text-zinc-600'">S</kbd> to save</span>
    </div>

    <!-- Right: Actions Toolbar -->
    <div class="flex items-center gap-1.5 shrink-0">
        <!-- Save Status Indicator -->
        <div class="flex items-center gap-1.5 text-[11px] font-mono px-2 py-0.5 rounded transition-colors"
             :class="theme === 'dark' ? 'text-zinc-400 bg-zinc-900/60 border border-zinc-800' : 'text-zinc-600 bg-zinc-100 border border-zinc-200/80'"
             x-show="activeFile">
            <span class="size-1.5 rounded-full"
                  :class="{
                      'bg-zinc-400 dark:bg-zinc-500': saveStatus === 'Saved',
                      'bg-zinc-900 dark:bg-zinc-100 animate-pulse': saveStatus === 'Unsaved changes',
                      'bg-zinc-400': saveStatus === 'Saving'
                  }"></span>
            <span x-text="saveStatus || 'Ready'"></span>
        </div>

        <!-- Theme Toggle Button -->
        <button type="button" 
                @click="toggleTheme()" 
                class="p-1.5 rounded transition cursor-pointer"
                :class="theme === 'dark' ? 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800' : 'text-zinc-500 hover:text-zinc-800 hover:bg-zinc-200/60'"
                title="Toggle Editor Theme">
            <template x-if="theme === 'dark'">
                <x-lucide-sun class="size-3.5" />
            </template>
            <template x-if="theme === 'light'">
                <x-lucide-moon class="size-3.5" />
            </template>
        </button>

        <!-- Save File Button -->
        <button type="button" 
                @click="saveFile()"
                :disabled="!activeFile || saving || !isModified"
                class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded transition cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                :class="theme === 'dark' ? 'bg-zinc-800 text-zinc-100 hover:bg-zinc-700 border border-zinc-700' : 'bg-zinc-900 text-white hover:bg-zinc-800 border border-zinc-900'">
            <template x-if="saving">
                <span class="animate-spin inline-block size-3.5 border-2 border-white/30 border-t-white rounded-full"></span>
            </template>
            <template x-if="!saving">
                <x-lucide-save class="size-3.5" />
            </template>
            <span x-text="saving ? 'Saving' : 'Save'"></span>
        </button>

        <!-- Visit Site Link -->
        <a href="https://{{ $deployment->domain }}" 
           target="_blank" 
           rel="noopener"
           class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded border transition"
           :class="theme === 'dark' ? 'text-zinc-300 hover:text-white bg-zinc-900/60 border-zinc-700 hover:bg-zinc-800' : 'text-zinc-700 hover:text-zinc-900 bg-white border-zinc-200 hover:bg-zinc-50'">
            <span>Preview</span>
            <x-lucide-external-link class="size-3 text-zinc-400" />
        </a>
    </div>
</div>
