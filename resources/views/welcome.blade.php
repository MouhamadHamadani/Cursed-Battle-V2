<x-guest-layout>
    <div class="text-center">
        {{-- Gothic accents: arch + seal are the focal points, scrollwork and
             chain stay thin. Nothing sits behind the pitch copy or the CTAs. --}}
        <x-gothic-arch-frame>
            <x-wax-seal :size="52" class="mx-auto block" />

            <x-tattered-banner class="mt-4">
                <x-label class="font-uncialAntiqua text-4xl text-yellow-500 text-shadow-lg shadow-yellow-600">
                    {{ config('app.name', 'Cursed Battle') }}
                </x-label>
            </x-tattered-banner>
        </x-gothic-arch-frame>

        <x-chain-divider class="my-6" />

        <x-iron-scrollwork>
            <x-dark-leather class="border border-yellow-700/40 px-8 py-5">
                <p class="font-sans text-sm text-stone-300">
                    {{ __('Work for coin, drill for strength, arm thyself, and settle it in the ring.') }}
                </p>
            </x-dark-leather>
        </x-iron-scrollwork>

        <div class="flex justify-center gap-4 mt-8">
            @auth
                <a href="{{ route('home') }}" wire:navigate>
                    <x-button type="button">
                        <i class="fa-duotone fa-solid fa-swords me-2"></i>{{ __('Continue') }}
                    </x-button>
                </a>
            @else
                <a href="{{ route('login') }}" wire:navigate>
                    <x-button type="button">{{ __('Log in') }}</x-button>
                </a>

                @if (Route::has('register'))
                    <a href="{{ route('register') }}" wire:navigate>
                        <x-secondary-button>{{ __('Register') }}</x-secondary-button>
                    </a>
                @endif
            @endauth
        </div>
    </div>
</x-guest-layout>
