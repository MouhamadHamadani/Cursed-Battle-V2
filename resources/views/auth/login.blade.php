<x-guest-layout>
    {{-- Arch + chain mirror the landing page so the auth funnel reads as the
         same place. The arch frames the heading only, not the fields: its head
         sweeps ~80% of the box height, so a form-tall box would drag the curve
         straight through the inputs. --}}
    <x-gothic-arch-frame class="text-center">
        <x-label class="text-3xl text-yellow-500 text-shadow-lg shadow-yellow-600">{{ __('Enter the Fray') }}</x-label>
        <p class="mt-2 font-sans text-sm text-stone-300">{{ __('Your champion awaits your command.') }}</p>
    </x-gothic-arch-frame>

    <x-chain-divider class="mb-6" />

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input icon="fa-envelope" id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input icon="fa-lock" id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="bg-black/60 border-yellow-700 text-yellow-600 shadow-sm focus:ring-yellow-600" name="remember">
                <span class="ms-2 font-sans text-sm text-stone-300">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-end mt-4">
            @if (Route::has('password.request'))
                <a class="underline font-newRocker text-sm text-yellow-500 hover:text-yellow-300 focus:outline-none" href="{{ route('password.request') }}" wire:navigate>
                    {{ __('Forgot your password?') }}
                </a>
            @endif

            <x-primary-button class="ms-3">
                {{ __('Log in') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
