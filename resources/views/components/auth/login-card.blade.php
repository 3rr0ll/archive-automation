<form @submit.prevent="submit"
      novalidate
      {{ $attributes->merge(['class' => 'w-full max-w-[380px] mx-auto space-y-4 font-sans']) }}
      x-data="loginForm">
    <!-- Header -->
    <div class="text-center mb-6">
        <h1 class="text-xl font-semibold tracking-tight text-zinc-900 font-sans">Sign in to Archive Automation</h1>
        <p class="text-xs text-zinc-500 mt-1">Enter your credentials to access your deployment dashboard.</p>
    </div>

    <!-- Error Banner -->
    <div x-show="errorMessage"
         x-cloak
         class="p-3 text-xs text-red-700 bg-red-50 border border-red-200 rounded-md">
        <span x-text="errorMessage"></span>
    </div>

    <!-- Email Field -->
    <div>
        <label for="login-email" class="block text-xs font-semibold text-zinc-700 uppercase tracking-wider mb-1.5">
            Email
        </label>
        <input id="login-email"
               type="email"
               x-model="email"
               @blur="if (email.trim()) validateEmail()"
               @input="if (emailError) validateEmail()"
               autofocus
               autocomplete="email"
               placeholder="email@example.com"
               required
               class="block w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 placeholder:text-zinc-400 focus:border-zinc-400 focus:outline-none transition">
        <span x-show="emailError"
              x-cloak
              class="text-[11px] text-red-500 mt-1 block font-medium"
              x-text="emailError"></span>
    </div>

    <!-- Password Field with show/hide toggle -->
    <div>
        <label for="login-password" class="block text-xs font-semibold text-zinc-700 uppercase tracking-wider mb-1.5">
            Password
        </label>
        <div class="relative">
            <input id="login-password"
                   x-bind:type="showPassword ? 'text' : 'password'"
                   x-model="password"
                   autocomplete="current-password"
                   placeholder="••••••••"
                   required
                   class="block w-full rounded-md border border-zinc-200 bg-white px-3 py-2 pr-10 text-sm text-zinc-900 placeholder:text-zinc-400 focus:border-zinc-400 focus:outline-none transition">
            <button type="button"
                    @click="showPassword = !showPassword"
                    aria-label="Toggle password visibility"
                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-zinc-400 hover:text-zinc-700 cursor-pointer focus:outline-none transition-colors">
                <x-lucide-eye-off x-show="!showPassword" class="size-4" />
                <x-lucide-eye x-show="showPassword" x-cloak class="size-4" />
            </button>
        </div>
    </div>

    <!-- Submit Button -->
    <button type="submit"
            :disabled="isLoading || !email?.trim() || !password"
            class="w-full mt-2 inline-flex items-center justify-center px-4 py-2 rounded-md text-sm font-medium text-white bg-zinc-900 hover:bg-zinc-800 disabled:opacity-50 focus:outline-none transition cursor-pointer">
        <span x-show="isLoading"
              x-cloak
              class="animate-spin inline-block size-3.5 border-2 border-white border-t-transparent rounded-full mr-2"></span>
        <span x-text="isLoading ? 'Signing In' : 'Sign In'">Sign In</span>
    </button>
</form>
