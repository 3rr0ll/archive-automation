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
                    <x-lucide-eye-off x-show="!showPassword" class="size-4" />
                    <x-lucide-eye x-show="showPassword" x-cloak class="size-4" />
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
