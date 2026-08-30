<x-guest-layout>
    {{-- Registration is the character-creation flow: a character with its
         starting stats is forged the moment the account is created. --}}
    <div class="text-center mb-6">
        <x-label class="text-3xl text-yellow-500 text-shadow-lg shadow-yellow-600">{{ __('Forge Your Champion') }}</x-label>
        <p class="mt-2 font-sans text-sm text-stone-300">{{ __('Name thyself, and a fighter shall be raised in thy stead.') }}</p>
    </div>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

            <x-text-input id="password_confirmation" class="block mt-1 w-full"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        {{-- Faction: permanent, and it decides which wares the market shows.
             Labels here are placeholders — display naming is still TBD, so
             only the generic DB keys are committed to. --}}
        <fieldset class="mt-6">
            <legend class="block font-newRocker text-yellow-500 tracking-wide">{{ __('Choose Thy Faction') }}</legend>
            <p class="mt-1 font-sans text-xs text-stone-400">{{ __('Chosen once, and never again. Thy faction decides what the market will sell thee.') }}</p>

            <div class="mt-3 grid grid-cols-2 gap-3">
                @foreach (\App\Models\Character::FACTIONS as $index => $faction)
                    <label class="cursor-pointer">
                        <input type="radio" name="faction" value="{{ $faction }}" class="peer sr-only" required
                               @checked(old('faction') === $faction)>
                        <x-dark-wall class="border border-yellow-700 p-4 text-center text-stone-300 transition duration-300 hover:border-yellow-500 peer-checked:text-yellow-500 peer-checked:border-yellow-400 peer-focus:ring-1 peer-focus:ring-yellow-500">
                            <span class="block font-newRocker text-xl">
                                {{ __('Faction :number', ['number' => $index + 1]) }}
                            </span>
                        </x-dark-wall>
                    </label>
                @endforeach
            </div>

            <x-input-error :messages="$errors->get('faction')" class="mt-2" />
        </fieldset>

        <div class="flex items-center justify-end mt-4">
            <a class="underline font-newRocker text-sm text-yellow-500 hover:text-yellow-300 focus:outline-none" href="{{ route('login') }}" wire:navigate>
                {{ __('Already registered?') }}
            </a>

            <x-primary-button class="ms-4">
                {{ __('Register') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
