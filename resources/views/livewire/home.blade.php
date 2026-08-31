<div>
    @if ($character)
        @php
            $xpThreshold = app(\App\Services\LevelingService::class)->threshold($character->level);
            $pct = fn (int $value, int $max) => $max > 0 ? min(100, max(0, round($value / $max * 100))) : 0;
        @endphp

        @if ($character->isHospitalized())
            <x-dark-wall class="border border-red-900 p-4 mb-6 text-center">
                <x-label class="text-xl text-red-500">
                    <i class="fa-duotone fa-solid fa-kit-medical me-2"></i>
                    {{ __('In hospital — released :time.', ['time' => $character->hospitalized_until->diffForHumans()]) }}
                </x-label>
            </x-dark-wall>
        @endif

        {{-- Vitals: the three bars a fighter reads first. --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <x-dark-wall class="border border-yellow-700 p-4">
                <div class="flex items-center justify-between mb-2">
                    <x-label class="text-yellow-500">
                        <i class="fa-duotone fa-solid fa-star me-2"></i>{{ __('XP') }}
                    </x-label>
                    <x-label>{{ $character->xp }} / {{ $xpThreshold }}</x-label>
                </div>
                <div class="h-3 bg-black border border-yellow-700">
                    <div class="h-full bg-yellow-500" style="width: {{ $pct($character->xp, $xpThreshold) }}%"></div>
                </div>
            </x-dark-wall>

            <x-dark-wall class="border border-yellow-700 p-4">
                <div class="flex items-center justify-between mb-2">
                    <x-label class="text-red-500">
                        <i class="fa-duotone fa-solid fa-heart me-2"></i>{{ __('Health') }}
                    </x-label>
                    <x-label>{{ $character->health }} / {{ $character->max_health }}</x-label>
                </div>
                <div class="h-3 bg-black border border-yellow-700">
                    <div class="h-full bg-red-500" style="width: {{ $pct($character->health, $character->max_health) }}%"></div>
                </div>
            </x-dark-wall>

            <x-dark-wall class="border border-yellow-700 p-4">
                <div class="flex items-center justify-between mb-2">
                    <x-label class="text-yellow-700">
                        <i class="fa-duotone fa-solid fa-bolt me-2"></i>{{ __('Energy') }}
                    </x-label>
                    <x-label>{{ $character->energy }} / {{ $character->max_energy }}</x-label>
                </div>
                <div class="h-3 bg-black border border-yellow-700">
                    <div class="h-full bg-yellow-700" style="width: {{ $pct($character->energy, $character->max_energy) }}%"></div>
                </div>
                <p class="mt-2 font-sans text-xs text-stone-400">{{ __('Health and energy return on the world tick.') }}</p>
            </x-dark-wall>
        </div>

        {{-- Faction. Click through for the banner's own page. --}}
        <button type="button" wire:click="$set('showFaction', true)" class="block w-full mb-8">
            <x-dark-wall class="border border-yellow-700 p-4 flex items-center justify-center gap-3 hover:border-yellow-500 transition duration-300">
                <i class="fa-duotone fa-solid fa-flag-swallowtail text-yellow-500"></i>
                <x-label class="text-yellow-500">{{ __('Faction') }}</x-label>
                <x-label class="text-xl">{{ \App\Models\Character::factionLabel($character->faction) }}</x-label>
            </x-dark-wall>
        </button>

        {{-- Standing and stats. --}}
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-6 text-center">
            {{-- Rank badge. Sized to the text-3xl line box on purpose so the
                 seal sits beside the number without making this one cell taller
                 than the four next to it. --}}
            <div>
                <x-label class="text-yellow-500">{{ __('Level') }}</x-label>
                <div class="flex items-center justify-center gap-2">
                    <x-wax-seal :size="36" />
                    <x-label class="text-3xl">{{ $character->level }}</x-label>
                </div>
            </div>
            <div>
                <x-label class="text-yellow-500">
                    <i class="fa-duotone fa-solid fa-coins me-2"></i>{{ __('Gold') }}
                </x-label>
                <x-label class="text-3xl">{{ $character->gold }}</x-label>
            </div>
            <div>
                <x-label class="text-yellow-500">{{ __('Strength') }}</x-label>
                <x-label class="text-3xl">{{ $character->strength }}</x-label>
            </div>
            <div>
                <x-label class="text-yellow-500">{{ __('Defense') }}</x-label>
                <x-label class="text-3xl">{{ $character->defense }}</x-label>
            </div>
            <div>
                <x-label class="text-yellow-500">{{ __('Agility') }}</x-label>
                <x-label class="text-3xl">{{ $character->agility }}</x-label>
            </div>
        </div>

        <x-dark-modal wire:model.live="showFaction" maxWidth="lg">
            <div class="p-8 text-center">
                <x-label class="font-uncialAntiqua text-3xl text-yellow-500">
                    {{ \App\Models\Character::factionLabel($character->faction) }}
                </x-label>

                {{-- Paired with the faction preview modal on the creation page:
                     same panel, same rule. --}}
                <x-chain-divider class="my-5" />

                <p class="font-sans text-sm text-stone-300">
                    {{ \App\Models\Character::factionDescription($character->faction) }}
                </p>

                @if ($factionCount !== null)
                    <div class="mt-6">
                        <x-label class="text-yellow-500">
                            <i class="fa-duotone fa-solid fa-users me-2"></i>{{ __('Soldiers under this banner') }}
                        </x-label>
                        <x-label class="block text-3xl">{{ $factionCount }}</x-label>
                    </div>
                @endif

                <div class="flex justify-center mt-8">
                    <x-button type="button" x-on:click="show = false">{{ __('Close') }}</x-button>
                </div>
            </div>
        </x-dark-modal>
    @else
        <x-label class="text-xl text-red-500 text-center">{{ __('No character found.') }}</x-label>
    @endif
</div>
