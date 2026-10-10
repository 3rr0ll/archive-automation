<form @submit.prevent="submit"
      novalidate
      {{ $attributes->merge(['class' => 'w-full max-w-[380px] mx-auto space-y-4 font-sans']) }}
      x-data="loginForm">
    <!-- Header -->
    <div class="text-center mb-6">
        <h1 class="text-2xl font-bold tracking-tight text-zinc-950">Sign in to Archive Automation</h1>
    </div>

    <!-- Error Banner -->
    <div x-show="errorMessage"
         x-cloak
         class="p-3 text-xs text-red-700 bg-red-50 border border-red-200 rounded-md">
        <span x-text="errorMessage"></span>
    </div>

    <!-- Email via TallStackUI Input -->
    <div>
        <x-input id="login-email"
                 type="email"
                 x-model="email"
                 @blur="if (email.trim()) validateEmail()"
                 @input="if (emailError) validateEmail()"
                 autofocus
                 autocomplete="email"
                 placeholder="email@example.com">
            <x-slot:label>
                <label for="login-email" class="block text-sm font-medium text-zinc-900 mb-1">
                    EMAIL
                </label>
            </x-slot:label>
        </x-input>
        <span x-show="emailError"
              x-cloak
              class="text-[11px] text-red-500 mt-1 block font-medium"
              x-text="emailError"></span>
    </div>

    <!-- Password via TallStackUI Input with show/hide toggle -->
    <div>
        <x-input id="login-password"
                 x-bind:type="showPassword ? 'text' : 'password'"
                 x-model="password"
                 autocomplete="current-password"
                 placeholder="••••••••">
            <x-slot:label>
                <label for="login-password" class="block text-sm font-medium text-zinc-900 mb-1">
                    PASSWORD
                </label>
            </x-slot:label>
            <x-slot:suffix>
                <button type="button"
                        @click="showPassword = !showPassword"
                        aria-label="Toggle password visibility"
                        class="px-2 text-zinc-400 hover:text-zinc-700 cursor-pointer focus:outline-hidden transition-colors">
                    <svg x-show="!showPassword" class="size-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/>
                    </svg>
                    <svg x-show="showPassword" x-cloak class="size-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </button>
            </x-slot:suffix>
        </x-input>
    </div>

    <!-- Submit Button via TallStackUI Button -->
    <x-button type="submit"
              color="black"
              class="w-full mt-2"
              x-bind:disabled="isLoading || !email?.trim() || !password">
        <span x-show="isLoading"
              x-cloak
              class="animate-spin inline-block size-3.5 border-2 border-zinc-500 border-t-white rounded-full"></span>
        <span x-text="isLoading ? 'Signing In...' : 'Sign In'">Sign In</span>
    </x-button>
</form>
