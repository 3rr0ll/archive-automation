<header class="sticky top-0 z-30 h-14 bg-[#fafafa]/90 backdrop-blur-md border-b border-zinc-200 flex items-center">
    <div class="w-full px-4 sm:px-6 lg:px-8 flex justify-between items-center">
        <!-- Left: Mobile controls & Breadcrumbs -->
        <div class="flex items-center gap-3">
            <!-- Mobile Hamburger -->
            <button type="button" @click="$dispatch('open-sidebar')" class="md:hidden p-1.5 text-zinc-500 hover:text-zinc-900 rounded-md hover:bg-zinc-200/50 transition cursor-pointer" aria-label="Open sidebar">
                <x-lucide-menu class="size-5" />
            </button>
        </div>
    </div>
</header>