<div>
    <x-slot name="header">
        <x-label as="h1" class="font-uncialAntiqua text-4xl sm:text-6xl text-yellow-500 text-shadow-lg shadow-yellow-600">
            {{ __('Train') }}
        </x-label>
    </x-slot>

    @php
        $cost = \App\Services\TrainingService::ENERGY_COST;
        // Speed is fa-wind, not fa-bolt. Home's stat row picked fa-wind and left
        // a note that Train should follow in its own pass: fa-bolt is Energy in
        // the status strip at the top of this very page, so a bolt on the Speed
        // card points at the wrong thing twice over. Dexterity keeps fa-feather
        // (ADR-003 gives dodge to dexterity, hit chance to speed).
        $stats = [
            'strength' => ['label' => __('Strength'), 'icon' => 'fa-hand-fist', 'action' => __('Train Strength')],
            'defense' => ['label' => __('Defense'), 'icon' => 'fa-shield-halved', 'action' => __('Train Defense')],
            'speed' => ['label' => __('Speed'), 'icon' => 'fa-wind', 'action' => __('Train Speed')],
            'dexterity' => ['label' => __('Dexterity'), 'icon' => 'fa-feather', 'action' => __('Train Dexterity')],
        ];
    @endphp

    <div class="py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

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
                    <x-label class="text-2xl text-red-500 mt-3">{{ $spent[array_rand($spent)] }}</x-label>
                </x-dark-wall>
            @endif

            <x-gem-divider class="mx-auto max-w-2xl" />

            <div class="flex flex-wrap justify-center gap-6">
                @foreach ($stats as $key => $stat)
                    @php
                        // Which rule closes this card, or null. Same named-reason
                        // shape as Home's quick links and Work's occupation cards.
                        // Training has no level gate, so there are only two.
                        // Both are TrainingService's own rules, read off the model.
                        $closed = $this->character->isBusy() ? 'busy'
                            : ($this->character->energy < $cost ? 'spent' : null);
                    @endphp

                    {{-- No hover promise on a closed card, and no per-card
                         <x-iron-scrollwork>: scrollwork wraps a page's outer
                         sheet, brass corners mark the cards inside it. The card
                         takes over the flex basis the wrapper held. --}}
                    <x-dark-wall @class([
                        'relative flex basis-full flex-col border p-6 transition duration-300 sm:basis-[calc(50%-0.75rem)] lg:basis-[calc(25%-1.125rem)]',
                        'border-yellow-700 hover:border-yellow-500' => ! $closed,
                        'border-stone-700' => (bool) $closed,
                    ])>
                        {{-- Both colours written out literally: <x-brass-corners>
                             reads its class through a prop, and Tailwind only
                             builds classes it can see in the calling file. --}}
                        <x-brass-corners class="h-3 w-3 {{ $closed ? 'text-stone-700' : 'text-yellow-700/70' }}" />

                        <div class="relative text-center">
                            <x-icon-roundel size="lg" :icon="$stat['icon']" :muted="(bool) $closed" />
                            <x-label @class(['text-2xl mt-3', 'text-stone-400' => (bool) $closed])>{{ $stat['label'] }}</x-label>
                        </div>

                        {{-- "Current" stays even though the band at the top of the
                             page shows the same four figures. They do different
                             jobs, the way Home's town map and quick-link cards do:
                             the band compares all four side by side, this is the
                             before-value at the point of action — and below sm the
                             cards stack, so by the fourth one the band has scrolled
                             off entirely. --}}
                        <div class="relative mt-3 space-y-1 text-center">
                            <x-label class="text-sm text-stone-400">
                                {{ __('Current') }}: <span class="text-white">{{ $this->character->{$key} }}</span>
                            </x-label>
                            <x-label class="text-sm text-stone-400">
                                {{ __('Costs') }} <span class="text-yellow-600">{{ $cost }}</span> {{ __('energy') }}
                            </x-label>
                        </div>

                        <div class="relative mt-auto pt-5">
                            @if ($closed === 'busy')
                                <x-button class="w-full" disable>
                                    <i class="fa-duotone fa-solid fa-hourglass-half me-2"></i>{{ __('Busy') }}
                                </x-button>
                            @elseif ($closed === 'spent')
                                {{-- Was the gap: below the energy cost this card
                                     stayed enabled and the click bounced off
                                     TrainingService with a flash error, while the
                                     panel above already said so. --}}
                                <x-button class="w-full" disable>
                                    <i class="fa-duotone fa-solid fa-bed me-2"></i>{{ __('Spent') }}
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
                @endforeach
            </div>

        </div>
    </div>
</div>
