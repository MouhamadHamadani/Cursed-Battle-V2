<div>
    <x-slot name="header">
        <x-label class="font-uncialAntiqua text-4xl sm:text-6xl text-yellow-500 text-shadow-lg shadow-yellow-600">
            {{ __('Train') }}
        </x-label>
    </x-slot>

    @php
        $cost = \App\Services\TrainingService::ENERGY_COST;
        $stats = [
            'strength' => ['label' => __('Strength'), 'icon' => 'fa-hand-fist', 'action' => __('Train Strength')],
            'defense' => ['label' => __('Defense'), 'icon' => 'fa-shield-halved', 'action' => __('Train Defense')],
            'agility' => ['label' => __('Agility'), 'icon' => 'fa-feather', 'action' => __('Train Agility')],
        ];
    @endphp

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <x-flash />

            <x-dark-leather class="border border-yellow-700 p-6">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 text-center">
                    @foreach ($stats as $key => $stat)
                        <div>
                            <x-label class="text-yellow-500">
                                <i class="fa-duotone fa-solid {{ $stat['icon'] }} me-2"></i>{{ $stat['label'] }}
                            </x-label>
                            <x-label class="text-3xl">{{ $this->character->{$key} }}</x-label>
                        </div>
                    @endforeach
                </div>
            </x-dark-leather>

            @if ($this->character->energy < $cost)
                {{-- Too spent to drill: V1's in-character blocking copy. --}}
                @php
                    $spent = [
                        'The training yard swims before thine eyes. Return when thy breath is thine own again.',
                        'Steel demands a steady hand, and thine is not. Rest, then take it up once more.',
                        'Thou hast drilled past thy limit. The next blow would teach thee nothing.',
                        'Even the master sleeps. Come back to the yard with strength to spend.',
                        'Thy wind is gone. Sit out this bout and let the hour restore thee.',
                    ];
                @endphp
                <x-dark-wall class="border border-red-900 p-6 text-center">
                    <i class="fa-duotone fa-solid fa-bed fa-2x text-red-500"></i>
                    <x-label class="text-xl text-red-500 mt-3">{{ $spent[array_rand($spent)] }}</x-label>
                </x-dark-wall>
            @endif

            <x-divider />

            <div class="flex flex-wrap justify-center gap-6">
                @foreach ($stats as $key => $stat)
                    <x-dark-wall class="flex flex-col basis-full md:basis-[calc(33.333%-1rem)] border border-yellow-700 p-6 hover:border-yellow-500 transition duration-300">
                        <div class="text-center">
                            <i class="fa-duotone fa-solid {{ $stat['icon'] }} fa-3x text-yellow-500"></i>
                            <x-label class="text-2xl mt-3">{{ $stat['label'] }}</x-label>
                        </div>

                        <div class="mt-3 space-y-1 text-center">
                            <x-label class="text-sm text-stone-400">
                                {{ __('Current') }}: <span class="text-white">{{ $this->character->{$key} }}</span>
                            </x-label>
                            <x-label class="text-sm text-stone-400">
                                {{ __('Costs') }} <span class="text-yellow-700">{{ $cost }}</span> {{ __('energy') }}
                            </x-label>
                        </div>

                        <div class="mt-auto pt-5">
                            <x-button
                                class="w-full"
                                target="train"
                                wire:click="train('{{ $key }}')"
                                wire:loading.attr="disabled"
                            >
                                {{ $stat['action'] }}
                            </x-button>
                        </div>
                    </x-dark-wall>
                @endforeach
            </div>

        </div>
    </div>
</div>
