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
            'speed' => ['label' => __('Speed'), 'icon' => 'fa-bolt', 'action' => __('Train Speed')],
            'dexterity' => ['label' => __('Dexterity'), 'icon' => 'fa-feather', 'action' => __('Train Dexterity')],
        ];
    @endphp

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <x-flash />

            <x-dark-leather class="border border-yellow-700 p-6">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-6 text-center">
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

            @if ($this->character->isBusy())
                {{-- V1's training-in-progress copy, lifted from the training
                     flavour array in V1's battle.blade.php. --}}
                @php
                    $drilling = [
                        'Thou art in the midst of rigorous training. Return anon to see thine progress.',
                        'The training grounds are alive with thy efforts. Patience, for thy skills are yet honing.',
                        'Engrossed in thy training, thou must wait for the completion of this arduous task.',
                        'In the heart of training, thou art. Await the end of this endeavor to continue thy journey.',
                        'The echoes of thy training resonate. Return when the echo fades and thy training is complete.',
                    ];
                @endphp
                {{-- Chain on the blocked panels only, as on Work. --}}
                <x-dark-wall class="border border-yellow-700 p-6 text-center">
                    <i class="fa-duotone fa-solid fa-dumbbell fa-2x text-yellow-500"></i>
                    <x-chain-divider class="mt-4" />
                    <x-label class="text-xl text-yellow-500 mt-3">{{ $drilling[array_rand($drilling)] }}</x-label>
                    <x-label class="text-4xl mt-4">
                        <x-activity-countdown :completes-at="$this->character->activity_completes_at" />
                    </x-label>
                    @if ($this->character->activity_stat)
                        <x-label class="text-sm text-stone-400 mt-2">{{ __('Thy drill') }}: {{ ucfirst($this->character->activity_stat) }}</x-label>
                    @endif
                </x-dark-wall>
            @elseif ($this->character->energy < $cost)
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
                    <x-chain-divider class="mt-4" />
                    <x-label class="text-xl text-red-500 mt-3">{{ $spent[array_rand($spent)] }}</x-label>
                </x-dark-wall>
            @endif

            <x-divider />

            <div class="flex flex-wrap justify-center gap-6">
                @foreach ($stats as $key => $stat)
                    <x-iron-scrollwork class="grid basis-full sm:basis-[calc(50%-0.75rem)] lg:basis-[calc(25%-1.125rem)]">
                    <x-dark-wall class="flex flex-col border border-yellow-700 p-6 hover:border-yellow-500 transition duration-300">
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
                            @if ($this->character->isBusy())
                                <x-button class="w-full" disable>
                                    <i class="fa-duotone fa-solid fa-hourglass-half me-2"></i>{{ __('Busy') }}
                                </x-button>
                            @else
                                <x-button
                                    class="w-full"
                                    target="train"
                                    wire:click="train('{{ $key }}')"
                                    wire:loading.attr="disabled"
                                >
                                    {{ $stat['action'] }}
                                </x-button>
                            @endif
                        </div>
                    </x-dark-wall>
                    </x-iron-scrollwork>
                @endforeach
            </div>

        </div>
    </div>
</div>
