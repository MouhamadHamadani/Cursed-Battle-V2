<div>
    <x-slot name="header">
        <x-label as="h1" class="font-uncialAntiqua text-4xl sm:text-6xl text-yellow-500 text-shadow-lg shadow-yellow-600">
            {{ __('Battle') }}
        </x-label>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

            @php
                // The three locks CombatService and OpponentService enforce on
                // the attacker. Read off the model — this view decides nothing,
                // it only says out loud what the services already refuse.
                //
                // Battle is the one page hospital actually closes (ADR-001), and
                // until now it said nothing at all: Attack and Seek stayed lit
                // and the click bounced off a flash error.
                $hospitalized = $this->character->isHospitalized();
                $busy = $this->character->isBusy();
                $noHealth = $this->character->health <= 0;

                // Which reason the buttons name. Busy outranks hospital, the same
                // precedence Home's Battle quick-link uses — busy is the broader
                // lock. Both banners still show below when both are true.
                // ADR-002 fork 1: busy blocks attacking, not being attacked.
                $cannotFight = $busy ? 'busy'
                    : ($hospitalized ? 'hospital'
                    : ($noHealth ? 'health' : null));

                // Searching is blocked by the same two states but not by health —
                // OpponentService::find() lets a spent fighter line up a mark.
                $cannotSearch = $busy ? 'busy' : ($hospitalized ? 'hospital' : null);
            @endphp

            <x-flash />

            {{-- Status banners, the pair Home renders and for the same reason:
                 the banner says what is true and until when, the controls below
                 say which action it closes. Both can be true at once. --}}
            @if ($hospitalized)
                <x-dark-wall class="border border-red-900 p-4 text-center">
                    <x-label class="text-2xl text-red-500">
                        <i class="fa-duotone fa-solid fa-kit-medical me-2"></i>
                        {{ __('In hospital — released :time.', ['time' => $this->character->hospitalized_until->diffForHumans()]) }}
                    </x-label>
                    {{-- Home's banner states it and stops, which is right on a page
                         hospital does not close. Here it does, so the banner also
                         points at the ward — the bedside panel and the countdown. --}}
                    <div class="mt-4">
                        <x-button :href="route('hospital')" wire:navigate>
                            <i class="fa-duotone fa-solid fa-kit-medical me-2"></i>{{ __('To the Hospital') }}
                        </x-button>
                    </div>
                </x-dark-wall>
            @endif

            @if ($busy)
                {{-- Gold, not red: a session in progress is the character working
                     as intended, not a punishment. Hospital keeps the red. --}}
                <x-dark-wall class="border border-yellow-700 p-4 text-center">
                    <x-label class="text-2xl text-yellow-500">
                        <i class="fa-duotone fa-solid {{ $this->character->activity_type === 'work' ? 'fa-hammer' : 'fa-dumbbell' }} me-2"></i>
                        {{ ucfirst(\App\Services\ActivityService::describe($this->character->activity_type)) }}
                    </x-label>
                    <x-label class="text-4xl mt-2">
                        <x-activity-countdown :completes-at="$this->character->activity_completes_at" />
                    </x-label>
                </x-dark-wall>
            @endif

            {{-- The challenger --}}
            <x-dark-leather class="border border-yellow-700 p-6">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-6 text-center">
                    <div>
                        <x-label class="text-yellow-500">{{ __('Name') }}</x-label>
                        <x-label class="text-2xl">{{ $this->character->user->name }}</x-label>
                    </div>
                    <div>
                        <x-label class="text-yellow-500">{{ __('Level') }}</x-label>
                        <x-label class="text-2xl">{{ $this->character->level }}</x-label>
                    </div>
                    <div>
                        <x-label class="text-red-500">
                            <i class="fa-duotone fa-solid fa-heart me-2"></i>{{ __('Health') }}
                        </x-label>
                        <x-label class="text-2xl">{{ $this->character->health }} / {{ $this->character->max_health }}</x-label>
                    </div>
                    <div>
                        <x-label class="text-yellow-500">
                            <i class="fa-duotone fa-solid fa-coins me-2"></i>{{ __('Gold') }}
                        </x-label>
                        <x-label class="text-2xl">{{ $this->character->gold }}</x-label>
                    </div>
                </div>
            </x-dark-leather>

            <x-divider />

            {{-- The mark. One at a time, held on the character row. --}}
            <div>
                <x-label as="h2" class="font-uncialAntiqua text-3xl text-yellow-500 text-center mb-6">
                    <i class="fa-duotone fa-solid fa-swords me-2"></i>{{ __('Thy Mark') }}
                </x-label>

                <div class="flex justify-center">
                    @if ($this->opponent)
                        {{-- Hoisted, and bound with :disabled rather than the
                             @disabled directive: a Blade directive inside a
                             component tag's attributes stops the tag compiler
                             from matching the opening tag while the closing one
                             still compiles, which silently unbalances the @if. --}}
                        @php $tooPoorToReroll = $this->character->gold < $this->searchCost; @endphp

                        <x-dark-wall wire:key="opponent-{{ $this->opponent->id }}"
                                     class="flex flex-col basis-full sm:basis-2/3 lg:basis-1/2 border border-yellow-700 p-6 hover:border-red-500 transition duration-300">
                            <div class="text-center">
                                <i class="fa-duotone fa-solid fa-helmet-battle fa-4x text-yellow-500"></i>
                                <x-label class="text-2xl mt-3">{{ $this->opponent->user->name ?? __('Unknown') }}</x-label>
                            </div>

                            <div class="mt-4 grid grid-cols-2 gap-4 text-center">
                                <div>
                                    <x-label class="text-sm text-stone-400">{{ __('Level') }}</x-label>
                                    <x-label class="text-xl">{{ $this->opponent->level }}</x-label>
                                </div>
                                <div>
                                    <x-label class="text-sm text-stone-400">
                                        <i class="fa-duotone fa-solid fa-heart text-red-500 me-1"></i>{{ __('Health') }}
                                    </x-label>
                                    <x-label class="text-xl">{{ $this->opponent->health }} / {{ $this->opponent->max_health }}</x-label>
                                </div>
                            </div>

                            <div class="mt-auto pt-6 flex flex-col sm:flex-row gap-3">
                                @if ($cannotFight === 'busy')
                                    <x-button class="flex-1" disable>
                                        <i class="fa-duotone fa-solid fa-hourglass-half me-2"></i>{{ __('Busy') }}
                                    </x-button>
                                @elseif ($cannotFight === 'hospital')
                                    <x-button class="flex-1" disable>
                                        <i class="fa-duotone fa-solid fa-kit-medical me-2"></i>{{ __('In hospital') }}
                                    </x-button>
                                @elseif ($cannotFight === 'health')
                                    <x-button class="flex-1" disable>
                                        <i class="fa-duotone fa-solid fa-heart-crack me-2"></i>{{ __('No health to fight') }}
                                    </x-button>
                                @else
                                    <x-button
                                        class="flex-1"
                                        target="attack"
                                        wire:click="attack"
                                        wire:loading.attr="disabled"
                                    >
                                        <i class="fa-duotone fa-solid fa-swords me-2"></i>{{ __('Attack') }}
                                    </x-button>
                                @endif

                                {{-- Rejecting this face is what costs; the price
                                     climbs until a fight is actually fought. --}}
                                <x-secondary-button
                                    class="flex-1"
                                    wire:click="search"
                                    wire:loading.attr="disabled"
                                    :disabled="$tooPoorToReroll || (bool) $cannotSearch"
                                >
                                    <i class="fa-duotone fa-solid fa-arrows-rotate me-2"></i>{{ __('Seek Another') }}
                                    <span class="ms-2 text-white">
                                        <i class="fa-duotone fa-solid fa-coins text-yellow-500 me-1"></i>{{ $this->searchCost }}
                                    </span>
                                </x-secondary-button>
                            </div>
                        </x-dark-wall>
                    @else
                        <x-dark-wall class="basis-full sm:basis-2/3 lg:basis-1/2 border border-yellow-700 p-8 text-center">
                            <i class="fa-duotone fa-solid fa-magnifying-glass fa-4x text-yellow-500"></i>
                            <x-label class="block text-2xl mt-4">{{ __('No mark before thee') }}</x-label>
                            <p class="mt-2 font-sans text-xs text-stone-400">
                                {{ __('The first search costs nothing. Turning thy nose up at what it finds does.') }}
                            </p>

                            <div class="mt-6">
                                @if ($cannotSearch === 'busy')
                                    <x-button disable>
                                        <i class="fa-duotone fa-solid fa-hourglass-half me-2"></i>{{ __('Busy') }}
                                    </x-button>
                                @elseif ($cannotSearch === 'hospital')
                                    <x-button disable>
                                        <i class="fa-duotone fa-solid fa-kit-medical me-2"></i>{{ __('In hospital') }}
                                    </x-button>
                                @else
                                    <x-button target="search" wire:click="search" wire:loading.attr="disabled">
                                        <i class="fa-duotone fa-solid fa-magnifying-glass me-2"></i>{{ __('Seek an Opponent') }}
                                    </x-button>
                                @endif
                            </div>
                        </x-dark-wall>
                    @endif
                </div>
            </div>

        </div>
    </div>

    {{-- Result --}}
    @if ($lastFight)
        @php
            // xp_change is non-zero only when the attacker won (CombatResult),
            // so it is an exact win test without needing character ids here.
            $won = $lastFight['xp_change'] > 0;
            $banner = ($won ? 'victory' : 'defeated').' ('.rand(1, 2).').png';
        @endphp

        <x-dark-modal wire:model.live="showResult" maxWidth="5xl">
            <div class="relative h-80 bg-center bg-no-repeat bg-cover"
                 style="background-image: url('{{ asset('images/'.$banner) }}');">
                <div class="absolute inset-0 flex items-center justify-center bg-black/40">
                    {{-- Fixed width rather than a max-w override: the arch needs a
                         box narrower than the component default to keep a tall
                         head, and w-* can't collide with its own max-w-[400px]. --}}
                    <x-gothic-arch-frame class="w-[280px]">
                        <x-label class="font-uncialAntiqua text-5xl text-center text-shadow-lg {{ $won ? 'text-yellow-500 shadow-yellow-600' : 'text-red-500 shadow-red-900' }}">
                            {{ $won ? __('Victory') : __('Defeated') }}
                        </x-label>
                    </x-gothic-arch-frame>
                </div>
            </div>

            <div class="p-5">
                <x-label class="font-uncialAntiqua text-3xl text-center text-yellow-500">{{ __('Battle Result') }}</x-label>

                <x-chain-divider class="my-5" />

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-6 text-center">
                    <div>
                        <x-label class="text-sm text-stone-400">{{ __('Winner') }}</x-label>
                        <x-label class="text-xl text-yellow-500">{{ $lastFight['winner_name'] }}</x-label>
                    </div>
                    <div>
                        <x-label class="text-sm text-stone-400">{{ __('Rounds') }}</x-label>
                        <x-label class="text-xl">{{ $lastFight['rounds'] }}</x-label>
                    </div>
                    <div>
                        <x-label class="text-sm text-stone-400">{{ __('Gold') }}</x-label>
                        <x-label class="text-xl {{ $lastFight['gold_change'] >= 0 ? 'text-green-500' : 'text-red-500' }}">
                            {{ $lastFight['gold_change'] >= 0 ? '+' : '' }}{{ $lastFight['gold_change'] }}
                        </x-label>
                    </div>
                    <div>
                        <x-label class="text-sm text-stone-400">{{ __('XP') }}</x-label>
                        <x-label class="text-xl {{ $lastFight['xp_change'] > 0 ? 'text-green-500' : 'text-stone-400' }}">
                            {{ $lastFight['xp_change'] >= 0 ? '+' : '' }}{{ $lastFight['xp_change'] }}
                        </x-label>
                    </div>
                </div>

                <div class="flex flex-wrap justify-center gap-4 mt-5">
                    @if ($lastFight['knockout'])
                        <x-label class="text-sm uppercase tracking-widest text-red-500">
                            <i class="fa-duotone fa-solid fa-skull me-2"></i>{{ __('Knockout') }}
                        </x-label>
                    @endif
                    @if ($lastFight['leveled_up'])
                        <x-label class="text-sm uppercase tracking-widest text-green-500">
                            <i class="fa-duotone fa-solid fa-arrow-up-right-dots me-2"></i>{{ __('Leveled up!') }}
                        </x-label>
                    @endif
                </div>

                <x-chain-divider class="my-5" />

                {{-- Round-by-round, told as chronicle rather than as a table. --}}
                <div class="max-h-72 overflow-y-auto no-scrollbar font-sans text-stone-300 space-y-2">
                    @foreach ($lastFight['events'] as $i => $event)
                        @php
                            $actor = $event['actor'] === 'attacker' ? $lastFight['attacker_name'] : $lastFight['defender_name'];
                            $target = $event['actor'] === 'attacker' ? $lastFight['defender_name'] : $lastFight['attacker_name'];
                        @endphp
                        <p wire:key="ev-{{ $i }}">
                            <span class="font-newRocker text-yellow-700">{{ __('Round') }} {{ $event['round'] }}</span> &mdash;
                            @if ($event['missed'] ?? false)
                                {{-- A miss is the attacker's failure; a dodge is the
                                     defender's success (ADR-003). Two different lines. --}}
                                {{ __(':actor swings wide, and the blow finds only air.', ['actor' => $actor]) }}
                            @elseif ($event['dodged'])
                                {{ __(':actor swings, and :target slips the blow.', ['actor' => $actor, 'target' => $target]) }}
                            @else
                                {{ __(':actor lands a blow for', ['actor' => $actor]) }}
                                <span class="text-red-500">{{ $event['damage'] }}</span>.
                            @endif
                            <span class="text-stone-400">
                                ({{ $target }} {{ __('at') }} {{ $event['target_hp'] }} {{ __('health') }})
                            </span>
                        </p>
                    @endforeach
                </div>

                <x-chain-divider class="my-5" />

                <x-label class="font-uncialAntiqua text-4xl text-center {{ $won ? 'text-yellow-500' : 'text-red-500' }}">
                    {{ $won ? __('You Won') : __('You Lost') }}
                </x-label>
            </div>

            <div class="flex justify-center pb-5">
                <x-button type="button" x-on:click="show = false">{{ __('Close') }}</x-button>
            </div>
        </x-dark-modal>
    @endif
</div>
