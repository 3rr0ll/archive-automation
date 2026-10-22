<!-- IDE Bottom Status Bar -->
<footer class="h-6 px-3 border-t flex items-center justify-between text-[11px] font-mono select-none shrink-0"
        :class="theme === 'dark' ? 'bg-[#18181b] border-zinc-800 text-zinc-400' : 'bg-zinc-100 border-zinc-200 text-zinc-600'">
    
    <!-- Left: Quick Editor Breadcrumb / Status -->
    <div class="flex items-center gap-2">
        <span class="text-xs text-zinc-400" x-show="activeFile" x-text="activeFile ? activeFile.name : 'Ready'"></span>
        <span class="text-xs text-zinc-500" x-show="!activeFile">Ready</span>
    </div>

    <!-- Right: Editor status / file specs -->
    <div class="flex items-center gap-3">
        <span x-show="activeFile" x-text="activeFile ? formatSize(activeFile.size) : ''"></span>
        <span class="text-zinc-400/50" x-show="activeFile">|</span>
        <span>UTF-8</span>
        <span class="text-zinc-400/50">|</span>
        <span class="uppercase" x-text="activeFile ? (activeFile.name.split('.').pop() || 'TEXT') : 'PLAIN'"></span>
    </div>
</footer>
