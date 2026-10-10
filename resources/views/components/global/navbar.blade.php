<header class="sticky top-0 z-30 h-14 bg-[#fafafa]/90 backdrop-blur-md border-b border-zinc-200 flex items-center">
    <div class="w-full px-4 sm:px-6 lg:px-8 flex justify-between items-center">
        <!-- Left: Mobile controls & Breadcrumbs -->
        <div class="flex items-center gap-3">
            <!-- Mobile Hamburger -->
            <button type="button" @click="tallStackUiMenuMobile = true" class="md:hidden p-1.5 text-zinc-500 hover:text-zinc-900 rounded-md hover:bg-zinc-200/50 transition cursor-pointer" aria-label="Open sidebar">
                <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 12h16M4 18h16" /></svg>
            </button>
        </div>
    </div>
</header>