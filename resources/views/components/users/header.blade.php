<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
    <div>
        <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 tracking-tight">Users</h1>
        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
            Manage user accounts, roles, access permissions, and passwords.
        </p>
    </div>
    <div class="flex items-center gap-3">
        <button type="button" 
                @click="openCreateModal()" 
                class="inline-flex items-center justify-center px-4 py-2 border border-transparent dark:border-zinc-700/80 text-sm font-medium rounded-lg text-white bg-zinc-900 hover:bg-zinc-800 focus:outline-none transition-colors shadow-sm cursor-pointer">
            <x-lucide-user-plus class="w-4 h-4 mr-2" />
            <span>Add User</span>
        </button>
    </div>
</div>
