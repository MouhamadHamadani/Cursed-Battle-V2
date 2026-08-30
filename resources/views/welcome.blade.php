<x-guest-layout>
    <div class="text-center">
        <x-label class="font-uncialAntiqua text-4xl text-yellow-500 text-shadow-lg shadow-yellow-600">
            {{ config('app.name', 'Cursed Battle') }}
        </x-label>

        <img class="my-5 mx-auto" src="{{ asset('images/border2.png') }}" alt="">

        <p class="font-sans text-sm text-stone-300">
            {{ __('Work for coin, drill for strength, arm thyself, and settle it in the ring.') }}
        </p>

        <div class="flex justify-center gap-4 mt-8">
            @auth
                <a href="{{ route('dashboard') }}" wire:navigate>
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
