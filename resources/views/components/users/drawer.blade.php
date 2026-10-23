<!-- User Create/Edit Drawer -->
<div x-show="showModal" style="display: none;" class="relative z-50" aria-labelledby="user-modal-title" role="dialog" aria-modal="true">
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
                     class="pointer-events-auto w-screen max-w-md">
                    <div class="flex h-full flex-col bg-white border-l border-zinc-200">
                        <!-- Drawer Header -->
                        <div class="px-6 py-5 flex items-center justify-between border-b border-zinc-100">
                            <h2 class="text-base font-semibold text-zinc-900" id="user-modal-title" x-text="modalMode === 'create' ? 'Add New User' : 'Edit User Profile'"></h2>
                            <button type="button" @click="closeModal()" class="rounded-md text-zinc-400 hover:text-zinc-600 focus:outline-none transition p-1 cursor-pointer">
                                <span class="sr-only">Close panel</span>
                                <x-lucide-x class="size-4.5" />
                            </button>
                        </div>

                        <!-- Drawer Body -->
                        <div class="flex-1 overflow-y-auto px-6 py-6">
                            <form @submit.prevent="submitUserForm" id="user-form" class="space-y-5">
                                <!-- Name -->
                                <div>
                                    <label for="user-name" class="block text-xs font-medium text-zinc-700 mb-1.5">
                                        Full Name <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" 
                                           id="user-name" 
                                           x-model="userForm.name" 
                                           placeholder="e.g. Jane Doe" 
                                           required
                                           class="block w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 placeholder:text-zinc-400 focus:border-zinc-400 focus:outline-none transition">
                                    <template x-if="formErrors.name">
                                        <p class="text-xs text-red-500 mt-1" x-text="formErrors.name[0]"></p>
                                    </template>
                                </div>

                                <!-- Email -->
                                <div>
                                    <label for="user-email" class="block text-xs font-medium text-zinc-700 mb-1.5">
                                        Email Address <span class="text-red-500">*</span>
                                    </label>
                                    <input type="email" 
                                           id="user-email" 
                                           x-model="userForm.email" 
                                           placeholder="jane@example.com" 
                                           required
                                           class="block w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 placeholder:text-zinc-400 focus:border-zinc-400 focus:outline-none transition">
                                    <template x-if="formErrors.email">
                                        <p class="text-xs text-red-500 mt-1" x-text="formErrors.email[0]"></p>
                                    </template>
                                </div>

                                <!-- Role Selection (regular users only) -->
                                <div x-show="modalMode === 'edit' && !editingUserIsAdmin">
                                    <label class="block text-xs font-medium text-zinc-700 mb-2">Role <span class="text-red-500">*</span></label>
                                    <div class="grid grid-cols-2 gap-3">
                                        <!-- User Role -->
                                        <label class="relative flex items-center justify-between p-3 rounded-lg border cursor-pointer transition text-left"
                                               :class="userForm.role === 'user' ? 'border-zinc-900 bg-zinc-50' : 'border-zinc-200 hover:border-zinc-300 bg-white'">
                                            <input type="radio" name="role" value="user" x-model="userForm.role" class="sr-only">
                                            <div>
                                                <div class="text-sm font-semibold text-zinc-900">User</div>
                                                <div class="text-[11px] text-zinc-500">Standard access</div>
                                            </div>
                                            <div class="size-3.5 rounded-full border flex items-center justify-center shrink-0"
                                                 :class="userForm.role === 'user' ? 'border-zinc-900 bg-zinc-900' : 'border-zinc-300'">
                                                <span x-show="userForm.role === 'user'" class="size-1.5 rounded-full bg-white"></span>
                                            </div>
                                        </label>

                                        <!-- Admin Role -->
                                        <label class="relative flex items-center justify-between p-3 rounded-lg border cursor-pointer transition text-left"
                                               :class="userForm.role === 'admin' ? 'border-zinc-900 bg-zinc-50' : 'border-zinc-200 hover:border-zinc-300 bg-white'">
                                            <input type="radio" name="role" value="admin" x-model="userForm.role" class="sr-only">
                                            <div>
                                                <div class="text-sm font-semibold text-zinc-900">Admin</div>
                                                <div class="text-[11px] text-zinc-500">Full system access</div>
                                            </div>
                                            <div class="size-3.5 rounded-full border flex items-center justify-center shrink-0"
                                                 :class="userForm.role === 'admin' ? 'border-zinc-900 bg-zinc-900' : 'border-zinc-300'">
                                                <span x-show="userForm.role === 'admin'" class="size-1.5 rounded-full bg-white"></span>
                                            </div>
                                        </label>
                                    </div>
                                    <template x-if="formErrors.role">
                                        <p class="text-xs text-red-500 mt-1" x-text="formErrors.role[0]"></p>
                                    </template>
                                </div>

                                <!-- Password fields (Only shown during Create mode) -->
                                <template x-if="modalMode === 'create'">
                                    <div class="space-y-4 pt-2 border-t border-zinc-100">
                                        <div>
                                            <label for="user-password" class="block text-xs font-medium text-zinc-700 mb-1.5">
                                                Password <span class="text-red-500">*</span>
                                            </label>
                                            <input type="password" 
                                                   id="user-password" 
                                                   x-model="userForm.password" 
                                                   placeholder="••••••••" 
                                                   required
                                                   class="block w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 placeholder:text-zinc-400 focus:border-zinc-400 focus:outline-none transition">
                                            <template x-if="formErrors.password">
                                                <p class="text-xs text-red-500 mt-1" x-text="formErrors.password[0]"></p>
                                            </template>
                                        </div>

                                        <div>
                                            <label for="user-password-confirmation" class="block text-xs font-medium text-zinc-700 mb-1.5">
                                                Confirm Password <span class="text-red-500">*</span>
                                            </label>
                                            <input type="password" 
                                                   id="user-password-confirmation" 
                                                   x-model="userForm.password_confirmation" 
                                                   placeholder="••••••••" 
                                                   required
                                                   class="block w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 placeholder:text-zinc-400 focus:border-zinc-400 focus:outline-none transition">
                                        </div>
                                    </div>
                                </template>
                            </form>
                        </div>

                        <!-- Drawer Footer -->
                        <div class="px-6 py-4 border-t border-zinc-100 flex items-center justify-end gap-3 bg-zinc-50">
                            <button type="button" 
                                    @click="closeModal()" 
                                    :disabled="isSubmitting"
                                    class="inline-flex items-center gap-1.5 px-4 py-2 border border-zinc-200 rounded-md text-sm font-medium text-zinc-700 bg-white hover:bg-zinc-50 focus:outline-none transition cursor-pointer disabled:opacity-50">
                                <x-lucide-x class="size-4 text-zinc-500" />
                                <span>Cancel</span>
                            </button>
                            <button type="submit" 
                                    form="user-form"
                                    :disabled="isSubmitting"
                                    class="px-4 py-2 border border-transparent rounded-md text-sm font-medium text-white bg-zinc-900 hover:bg-zinc-800 disabled:opacity-50 focus:outline-none transition flex items-center gap-2 cursor-pointer">
                                <span x-show="isSubmitting" class="animate-spin inline-block size-3.5 border-2 border-white border-t-transparent rounded-full"></span>
                                <x-lucide-user-plus x-show="!isSubmitting && modalMode === 'create'" class="size-4" />
                                <x-lucide-save x-show="!isSubmitting && modalMode !== 'create'" class="size-4" />
                                <span x-text="modalMode === 'create' ? (isSubmitting ? 'Creating' : 'Create User') : (isSubmitting ? 'Saving' : 'Save Changes')"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Password Reset Modal -->
<div x-show="showPasswordModal" style="display: none;" class="relative z-50" aria-labelledby="reset-modal-title" role="dialog" aria-modal="true">
    <div x-show="showPasswordModal" 
         x-transition.opacity 
         class="fixed inset-0 bg-black/40 transition-opacity" 
         @click="closePasswordModal()"></div>

    <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
            <div x-show="showPasswordModal"
                 x-transition:enter="ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="relative transform overflow-hidden rounded-2xl bg-white dark:bg-[#18181b] text-left transition-all sm:my-8 sm:w-full sm:max-w-md border border-zinc-200 dark:border-zinc-800 p-6 space-y-5 shadow-xl"
                 @click.stop>
                <div>
                    <h3 class="text-lg font-bold text-zinc-950 dark:text-zinc-100 tracking-tight" id="reset-modal-title">Reset Password</h3>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                        Set a new password for <span class="font-semibold text-zinc-800 dark:text-zinc-200" x-text="passwordForm.user_name"></span>.
                    </p>
                </div>

                <form @submit.prevent="submitPasswordReset" id="reset-password-form" class="space-y-4">
                    <div>
                        <label for="reset-password" class="block text-xs font-medium text-zinc-700 dark:text-zinc-300 mb-1.5">
                            New Password <span class="text-rose-500">*</span>
                        </label>
                        <input type="password" 
                               id="reset-password" 
                               x-model="passwordForm.password" 
                               placeholder="••••••••" 
                               required
                               class="block w-full rounded-md border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-[#18181b] px-3 py-2 text-sm text-zinc-900 dark:text-zinc-100 placeholder:text-zinc-400 dark:placeholder:text-zinc-500 focus:border-zinc-400 dark:focus:border-zinc-600 focus:outline-none transition">
                        <template x-if="passwordErrors.password">
                            <p class="text-xs text-rose-500 dark:text-rose-400 mt-1" x-text="passwordErrors.password[0]"></p>
                        </template>
                    </div>

                    <div>
                        <label for="reset-password-confirmation" class="block text-xs font-medium text-zinc-700 dark:text-zinc-300 mb-1.5">
                            Confirm New Password <span class="text-rose-500">*</span>
                        </label>
                        <input type="password" 
                               id="reset-password-confirmation" 
                               x-model="passwordForm.password_confirmation" 
                               placeholder="••••••••" 
                               required
                               class="block w-full rounded-md border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-[#18181b] px-3 py-2 text-sm text-zinc-900 dark:text-zinc-100 placeholder:text-zinc-400 dark:placeholder:text-zinc-500 focus:border-zinc-400 dark:focus:border-zinc-600 focus:outline-none transition">
                    </div>
                </form>

                <div class="pt-4 flex items-center justify-between border-t border-zinc-100 dark:border-zinc-800">
                    <button type="button" 
                            @click="closePasswordModal()" 
                            :disabled="isResettingPassword"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-white dark:bg-zinc-900 px-4 py-2 text-sm font-medium text-zinc-800 dark:text-zinc-200 border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-800 transition focus:outline-none disabled:opacity-50 cursor-pointer">
                        <x-lucide-x class="size-4 text-zinc-500 dark:text-zinc-400" />
                        <span>Cancel</span>
                    </button>
                    <button type="submit" 
                            form="reset-password-form" 
                            :disabled="isResettingPassword"
                            class="inline-flex items-center justify-center gap-1.5 rounded-lg px-5 py-2 text-sm font-medium text-white bg-zinc-900 hover:bg-zinc-800 dark:border dark:border-zinc-700/80 transition focus:outline-none disabled:opacity-50 cursor-pointer shadow-sm">
                        <span x-show="isResettingPassword" class="animate-spin inline-block size-3.5 border-2 border-white border-t-transparent rounded-full"></span>
                        <x-lucide-key-round x-show="!isResettingPassword" class="size-4" />
                        <span x-text="isResettingPassword ? 'Resetting' : 'Reset Password'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
