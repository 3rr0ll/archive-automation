@props([
    'title' => 'Sign In',
    'subtitle' => 'Archive Automation Console',
])

<div {{ $attributes->merge(['class' => 'w-full max-w-[380px] mx-auto bg-white border border-zinc-200 rounded-2xl p-6 sm:p-8 shadow-sm space-y-6']) }}
     style="background-color: #ffffff !important;"
     x-data="loginForm">


    <!-- Header / Branding -->
    <div class="text-center">
        <div class="inline-flex items-center justify-center size-10 rounded-xl bg-zinc-950 text-white shadow-xs mb-3">
            <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
            </svg>
        </div>
        <h1 class="text-xl font-semibold tracking-tight text-zinc-950 sm:text-2xl">{{ $title }}</h1>
        <p class="mt-1 text-xs text-zinc-500">{{ $subtitle }}</p>
    </div>

    <!-- Minimalist Light Error Alert -->
    <div x-show="errorMessage"
         x-cloak
         class="bg-zinc-50 border border-zinc-200 text-xs text-zinc-800 rounded-lg p-3 flex items-start gap-2.5"
         role="alert">
        <svg class="shrink-0 size-4 mt-0.5 text-zinc-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16" stroke-width="2"></line>
        </svg>
        <span class="flex-1 font-medium" x-text="errorMessage"></span>
    </div>

    <!-- Form -->
    <form @submit.prevent="submit" class="space-y-4">
        <!-- Email Input Group -->
        <div>
            <label for="login-email" class="block text-[11px] font-medium uppercase tracking-wider text-zinc-600 mb-1.5">
                Email
            </label>
            <input id="login-email"
                   type="email"
                   x-model="email"
                   required
                   autofocus
                   autocomplete="email"
                   placeholder="name@example.com"
                   style="background-color: #ffffff !important; color: #18181b !important;"
                   :class="fieldErrors.email ? 'border-zinc-500 focus:border-zinc-950 focus:ring-zinc-950' : 'border-zinc-300 focus:border-zinc-950 focus:ring-zinc-950'"
                   class="py-2.5 px-3.5 block w-full bg-white border rounded-lg text-sm text-zinc-900 placeholder-zinc-400 focus:outline-hidden focus:ring-1 transition-colors disabled:opacity-50" />
            <span x-show="fieldErrors.email"
                  x-cloak
                  class="text-[11px] text-zinc-600 mt-1.5 block font-medium"
                  x-text="fieldErrors.email?.[0]"></span>
        </div>

        <!-- Password Input Group with Toggle Password Feature -->
        <div>
            <div class="flex justify-between items-center mb-1.5">
                <label for="login-password" class="block text-[11px] font-medium uppercase tracking-wider text-zinc-600">
                    Password
                </label>
            </div>
            <div class="relative">
                <input id="login-password"
                       :type="showPassword ? 'text' : 'password'"
                       x-model="password"
                       required
                       autocomplete="current-password"
                       placeholder="••••••••"
                       style="background-color: #ffffff !important; color: #18181b !important;"
                       :class="fieldErrors.password ? 'border-zinc-500 focus:border-zinc-950 focus:ring-zinc-950' : 'border-zinc-300 focus:border-zinc-950 focus:ring-zinc-950'"
                       class="py-2.5 ps-3.5 pe-10 block w-full bg-white border rounded-lg text-sm text-zinc-900 placeholder-zinc-400 focus:outline-hidden focus:ring-1 transition-colors disabled:opacity-50" />

                <button type="button"
                        @click="showPassword = !showPassword"
                        aria-label="Toggle password visibility"
                        class="absolute inset-y-0 end-0 flex items-center z-20 px-3 cursor-pointer text-zinc-400 hover:text-zinc-700 focus:outline-hidden transition-colors">
                    <!-- Eye Off Icon (hidden when password visible) -->
                    <svg x-show="!showPassword" class="shrink-0 size-4" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"></path>
                        <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"></path>
                        <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"></path>
                        <line x1="2" x2="22" y1="2" y2="22"></line>
                    </svg>
                    <!-- Eye Icon (shown when password visible) -->
                    <svg x-show="showPassword" x-cloak class="shrink-0 size-4" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                </button>
            </div>
            <span x-show="fieldErrors.password"
                  x-cloak
                  class="text-[11px] text-zinc-600 mt-1.5 block font-medium"
                  x-text="fieldErrors.password?.[0]"></span>
        </div>

        <!-- High-Contrast Solid Black Action Button -->
        <button type="submit"
                :disabled="isLoading"
                class="w-full mt-2 py-2.5 px-4 inline-flex justify-center items-center gap-x-2 text-sm font-medium rounded-lg bg-zinc-950 text-white hover:bg-zinc-800 active:bg-black focus:outline-hidden transition-colors disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer shadow-xs">
            <span x-show="isLoading"
                  x-cloak
                  class="animate-spin inline-block size-3.5 border-2 border-zinc-500 border-t-white rounded-full"
                  role="status"
                  aria-label="loading"></span>
            <span x-text="isLoading ? 'Signing In...' : 'Sign In'"></span>
        </button>
    </form>

    <!-- Footer -->
    <div class="text-center pt-2">
        <p class="text-[11px] text-zinc-400 font-mono tracking-wider uppercase">Archive Automation</p>
    </div>
</div>
