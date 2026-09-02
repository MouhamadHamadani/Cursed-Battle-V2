<div>
    <x-slot name="header">
        <x-label as="h1" class="font-uncialAntiqua text-4xl sm:text-6xl text-yellow-500 text-shadow-lg shadow-yellow-600">
            {{ __('Hospital') }}
        </x-label>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

            @if ($this->character->isHospitalized())
                @php
                    // V1's in-character blocking copy, not a plain "please wait".
                    $bedside = [
                        'The surgeon bids thee lie still. Thy wounds are not yet closed.',
                        'Broth and bandages, traveller. The field will keep until thou art whole.',
                        'Thou wert carried here senseless. Be grateful, and be patient.',
                        'The bonesetter has done his work. Now the hours must do theirs.',
                        'No blade for thee today. Rest, and let the flesh knit.',
                    ];
                @endphp

                <x-iron-scrollwork>
                <x-dark-leather class="border border-red-900 p-10 text-center">
                    <i class="fa-duotone fa-solid fa-kit-medical fa-4x text-red-500"></i>

                    <x-label class="font-uncialAntiqua text-4xl text-red-500 mt-5">{{ __('You are hospitalized.') }}</x-label>

                    {{-- Chain only on the blocked branch, matching Work/Train:
                         the healthy panel below keeps the plain rule. --}}
                    <x-chain-divider class="my-5" />

                    <x-label class="text-xl text-yellow-500">{{ $bedside[array_rand($bedside)] }}</x-label>

                    <x-label class="text-2xl text-red-500 mt-5">
                        <i class="fa-duotone fa-solid fa-hourglass-half me-2"></i>
                        {{ __('You will be released :time.', ['time' => $this->character->hospitalized_until->diffForHumans()]) }}
                    </x-label>

                    <div class="mt-8 flex justify-center">
                        <x-dark-wall class="relative w-44 border border-yellow-700 p-4">
                            <x-brass-corners class="h-3 w-3 text-yellow-700/70" />
                            <div class="relative flex flex-col items-center text-center">
                                <x-icon-roundel icon="fa-heart" />
                                <x-label class="mt-2 text-xs uppercase tracking-widest text-yellow-600">{{ __('Health') }}</x-label>
                                <x-label class="text-3xl">{{ $this->character->health }} / {{ $this->character->max_health }}</x-label>
                            </div>
                        </x-dark-wall>
                    </div>

                    {{-- Hospital blocks combat and nothing else (ADR-001), so the
                         way out is not "wait here" — Work, Train and Market are
                         all still open. Home is where those four doors are, and
                         its quick-link cards already show which of them this
                         state closes. Without this the page stated the lockout
                         and stopped, leaving the nav bar as the only exit. --}}
                    <div class="mt-8">
                        <x-button :href="route('home')" wire:navigate>
                            <i class="fa-duotone fa-solid fa-house me-2"></i>{{ __('Back to Home') }}
                        </x-button>
                    </div>
                </x-dark-leather>
                </x-iron-scrollwork>
            @else
                <x-iron-scrollwork>
                <x-dark-leather class="border border-yellow-700 p-10 text-center">
                    <i class="fa-duotone fa-solid fa-shield-halved fa-4x text-green-500"></i>

                    <x-label class="font-uncialAntiqua text-4xl text-green-500 mt-5">{{ __('You are healthy and ready to fight.') }}</x-label>

                    {{-- Was a loose border2.png ornament — the one image rule
                         left in the app, at a fixed width that never matched the
                         panel. <x-gem-divider> is the section rule Home settled
                         on, and this branch is not blocked, so it is the gem and
                         not the chain (the chain means "bound" and stays on the
                         hospitalized panel above). --}}
                    <x-gem-divider class="my-6" />

                    <div class="mb-8 flex justify-center">
                        <x-dark-wall class="relative w-44 border border-yellow-700 p-4">
                            <x-brass-corners class="h-3 w-3 text-yellow-700/70" />
                            <div class="relative flex flex-col items-center text-center">
                                <x-icon-roundel icon="fa-heart" />
                                <x-label class="mt-2 text-xs uppercase tracking-widest text-yellow-600">{{ __('Health') }}</x-label>
                                <x-label class="text-3xl">{{ $this->character->health }} / {{ $this->character->max_health }}</x-label>
                            </div>
                        </x-dark-wall>
                    </div>

                    <x-button :href="route('battle')" wire:navigate>
                        <i class="fa-duotone fa-solid fa-swords me-2"></i>{{ __('Go to Battle') }}
                    </x-button>
                </x-dark-leather>
                </x-iron-scrollwork>
            @endif

        </div>
    </div>
</div>
