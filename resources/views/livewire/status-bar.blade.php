<div>
    @if ($this->character)
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-3">
            <x-dark-wall class="flex flex-wrap items-center justify-center gap-5 p-3 border border-yellow-700">
                <div class="flex flex-1 justify-center items-center gap-x-2" title="{{ __('Level') }}">
                    <i class="fa-duotone fa-solid fa-shield-halved text-yellow-500"></i>
                    <x-label>{{ $this->character->level }}</x-label>
                </div>

                <div class="flex flex-1 justify-center items-center gap-x-2" title="{{ __('XP') }}">
                    <i class="fa-duotone fa-solid fa-star text-yellow-500"></i>
                    <x-label>{{ $this->character->xp }} / {{ $this->xpThreshold }}</x-label>
                </div>

                <div class="flex flex-1 justify-center items-center gap-x-2" title="{{ __('Gold') }}">
                    <i class="fa-duotone fa-solid fa-coins text-yellow-500"></i>
                    <x-label>{{ $this->character->gold }}</x-label>
                </div>

                <div class="flex flex-1 justify-center items-center gap-x-2" title="{{ __('Health') }}">
                    <i class="fa-duotone fa-solid fa-heart text-red-500"></i>
                    <x-label>{{ $this->character->health }} / {{ $this->character->max_health }}</x-label>
                </div>

                <div class="flex flex-1 justify-center items-center gap-x-2" title="{{ __('Energy') }}">
                    <i class="fa-duotone fa-solid fa-bolt text-yellow-700"></i>
                    <x-label>{{ $this->character->energy }} / {{ $this->character->max_energy }}</x-label>
                </div>

                @if ($this->character->isHospitalized())
                    <div class="flex flex-1 justify-center items-center gap-x-2" title="{{ __('Hospitalized') }}">
                        <i class="fa-duotone fa-solid fa-kit-medical text-red-500"></i>
                        <x-label class="text-red-500">{{ $this->character->hospitalized_until->diffForHumans() }}</x-label>
                    </div>
                @endif
            </x-dark-wall>
        </div>
    @endif
</div>
