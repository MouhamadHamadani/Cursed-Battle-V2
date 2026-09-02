<div>
    <x-slot name="header">
        <x-label as="h1" class="font-uncialAntiqua text-4xl sm:text-6xl text-yellow-500 text-shadow-lg shadow-yellow-600">
            {{ __('Work') }}
        </x-label>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

            <x-flash />

            @if ($this->character->isBusy())
                {{-- V1's "thou art at work" full-lock panel — copy lifted from the
                     job flavour array in V1's battle.blade.php. --}}
                @php
                    $atWork = [
                        'Thou art diligently at work in thy chosen craft. Return when thy labors are complete.',
                        'Engrossed in thy duties, time must pass before thy task is done. Come back when thou hast finished.',
                        'As a dedicated artisan, thou art busy with thy work. Seek me out once thy job is fulfilled.',
                        'In the midst of thy toil, patience is required. Return to me once thy work is concluded.',
                        'The workshop calls for thy attention now. Once thy job is done, return to continue thy quest.',
                    ];
                    $currentJob = $this->occupations->firstWhere('id', $this->character->activity_occupation_id);
                @endphp
                {{-- Chain reads as "bound" — it goes on the blocked panels only,
                     never on the cards that still offer an action. --}}
                <x-dark-wall class="border border-yellow-700 p-6 text-center">
                    <i class="fa-duotone fa-solid fa-hammer fa-2x text-yellow-500"></i>
                    <x-chain-divider class="mt-4" />
                    <x-label class="text-xl text-yellow-500 mt-3">{{ $atWork[array_rand($atWork)] }}</x-label>
                    <x-label class="text-4xl mt-4">
                        <x-activity-countdown :completes-at="$this->character->activity_completes_at" />
                    </x-label>
                    @if ($currentJob)
                        <x-label class="text-sm text-stone-400 mt-2">{{ __('Thy trade') }}: {{ $currentJob->name }}</x-label>
                    @endif
                </x-dark-wall>
            @elseif ($this->character->energy < 1)
                {{-- Spent: V1's in-character blocking copy rather than "please wait". --}}
                @php
                    $spent = [
                        'Thy arms hang heavy, traveller. No guild will take thee in this state.',
                        'Thou hast given the day all thou hadst. Rest, and the strength returns.',
                        'The workshop bars its door to the exhausted. Come again with vigour.',
                        'Not a swing left in thee. Sit by the fire until thy wind comes back.',
                        'Thy hands shake too badly for honest labour. Wait for the hour to turn.',
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
                @foreach ($this->occupations as $occupation)
                    @php
                        $qualifies = $this->character->level >= $occupation->min_level
                            && ($occupation->max_level === null || $this->character->level <= $occupation->max_level);

                        // Which rule closes this card, or null. Same shape as
                        // Home's quick-link $closed: one reason per card, named,
                        // so the card can say which door is shut rather than
                        // going byte-identical and failing on click.
                        //
                        // Order keeps the existing busy-over-level precedence and
                        // slots the energy gate in last: a level-gated card says
                        // "Locked" even while the character is spent, because that
                        // is the standing reason and spending energy won't open it.
                        // Every one of these is WorkService's rule, read off the
                        // model — the view decides nothing.
                        $closed = $this->character->isBusy() ? 'busy'
                            : (! $qualifies ? 'locked'
                            : ($this->character->energy < 1 ? 'spent' : null));
                    @endphp

                    {{-- The scrollwork wrapper takes the flex basis and is a grid
                         so its single child fills it — the card still needs full
                         height for the mt-auto button row to sit on the bottom. --}}
                    <x-iron-scrollwork class="grid basis-full md:basis-[calc(50%-0.75rem)]">
                    {{-- No hover promise on a closed card — it neither lifts
                         nor brightens, the way Home demotes a blocked quick link. --}}
                    <x-dark-wall @class([
                        'flex flex-col border p-6 transition duration-300',
                        'border-yellow-700 hover:border-yellow-500' => ! $closed,
                        'border-stone-700' => (bool) $closed,
                    ])>
                        <div class="text-center">
                            <i class="fa-duotone fa-solid fa-hammer fa-2x {{ $closed ? 'text-stone-600' : 'text-yellow-500' }}"></i>
                            <x-label @class(['text-2xl mt-3', 'text-stone-400' => (bool) $closed])>{{ $occupation->name }}</x-label>
                        </div>

                        <p class="mt-3 font-sans text-sm text-stone-300">{{ $occupation->description }}</p>

                        <div class="mt-3 space-y-1">
                            <x-label class="text-sm text-stone-400">
                                {{ __('Level') }} {{ $occupation->min_level }}&ndash;{{ $occupation->max_level ?? '∞' }}
                            </x-label>
                            <x-label class="text-sm">
                                <span class="text-green-500">{{ $occupation->gold_per_energy }}</span>
                                <span class="text-stone-400">{{ __('gold/energy') }}</span>
                            </x-label>
                        </div>

                        <div class="mt-auto pt-5">
                            @if ($closed === 'busy')
                                <x-button class="w-full" disable>
                                    <i class="fa-duotone fa-solid fa-hourglass-half me-2"></i>{{ __('Busy') }}
                                </x-button>
                            @elseif ($closed === 'locked')
                                <x-button class="w-full" disable>
                                    <i class="fa-duotone fa-solid fa-lock me-2"></i>{{ __('Locked') }}
                                </x-button>
                            @elseif ($closed === 'spent')
                                {{-- Was the gap: at zero energy this card stayed
                                     enabled and the click bounced off
                                     WorkService with a flash error, while the
                                     panel above already said the character was
                                     spent. The card now agrees with the panel. --}}
                                <x-button class="w-full" disable>
                                    <i class="fa-duotone fa-solid fa-bed me-2"></i>{{ __('Spent') }}
                                </x-button>
                            @else
                                <x-button
                                    class="w-full"
                                    target="work"
                                    wire:click="work({{ $occupation->id }})"
                                    wire:loading.attr="disabled"
                                >
                                    {{ __('Work a shift') }}
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
