<div>
    <x-slot name="header">
        <x-label class="font-uncialAntiqua text-4xl sm:text-6xl text-yellow-500 text-shadow-lg shadow-yellow-600">
            {{ __('Battle') }}
        </x-label>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <x-flash />

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

            {{-- Opponents --}}
            <div>
                <x-label class="font-uncialAntiqua text-3xl text-yellow-500 text-center mb-6">
                    <i class="fa-duotone fa-solid fa-swords me-2"></i>{{ __('Opponents') }}
                </x-label>

                <div class="flex flex-wrap justify-center gap-6">
                    @forelse ($this->opponents as $opponent)
                        <x-dark-wall wire:key="opponent-{{ $opponent->id }}"
                                     class="flex flex-col basis-full sm:basis-[calc(50%-0.75rem)] lg:basis-[calc(33.333%-1rem)] border border-yellow-700 p-5 hover:border-red-500 transition duration-300">
                            <div class="text-center">
                                <i class="fa-duotone fa-solid fa-helmet-battle fa-3x text-yellow-500"></i>
                                <x-label class="text-xl mt-3">{{ $opponent->user->name ?? 'Unknown' }}</x-label>
                            </div>

                            <div class="mt-3 space-y-1 text-center">
                                <x-label class="text-sm text-stone-400">
                                    {{ __('Level') }} <span class="text-white">{{ $opponent->level }}</span>
                                </x-label>
                                <x-label class="text-sm text-stone-400">
                                    <i class="fa-duotone fa-solid fa-heart text-red-500 me-1"></i>
                                    <span class="text-white">{{ $opponent->health }} / {{ $opponent->max_health }}</span>
                                </x-label>
                            </div>

                            <div class="mt-auto pt-5">
                                <x-button
                                    class="w-full"
                                    target="attack"
                                    wire:click="attack({{ $opponent->id }})"
                                    wire:loading.attr="disabled"
                                >
                                    <i class="fa-duotone fa-solid fa-swords me-2"></i>{{ __('Attack') }}
                                </x-button>
                            </div>
                        </x-dark-wall>
                    @empty
                        <x-label class="text-stone-400">{{ __('No opponents available.') }}</x-label>
                    @endforelse
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
                    <x-label class="font-uncialAntiqua text-5xl text-center text-shadow-lg {{ $won ? 'text-yellow-500 shadow-yellow-600' : 'text-red-500 shadow-red-900' }}">
                        {{ $won ? __('Victory') : __('Defeated') }}
                    </x-label>
                </div>
            </div>

            <div class="p-5">
                <x-label class="font-uncialAntiqua text-3xl text-center text-yellow-500">{{ __('Battle Result') }}</x-label>

                <img class="my-5 mx-auto" src="{{ asset('images/border2.png') }}" alt="">

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

                <img class="my-5 mx-auto" src="{{ asset('images/border2.png') }}" alt="">

                {{-- Round-by-round, told as chronicle rather than as a table. --}}
                <div class="max-h-72 overflow-y-auto no-scrollbar font-sans text-stone-300 space-y-2">
                    @foreach ($lastFight['events'] as $i => $event)
                        @php
                            $actor = $event['actor'] === 'attacker' ? $lastFight['attacker_name'] : $lastFight['defender_name'];
                            $target = $event['actor'] === 'attacker' ? $lastFight['defender_name'] : $lastFight['attacker_name'];
                        @endphp
                        <p wire:key="ev-{{ $i }}">
                            <span class="font-newRocker text-yellow-700">{{ __('Round') }} {{ $event['round'] }}</span> &mdash;
                            @if ($event['dodged'])
                                {{ __(':actor swings, and :target slips the blow.', ['actor' => $actor, 'target' => $target]) }}
                            @else
                                {{ __(':actor lands a blow for', ['actor' => $actor]) }}
                                <span class="text-red-500">{{ $event['damage'] }}</span>.
                            @endif
                            <span class="text-stone-500">
                                ({{ $target }} {{ __('at') }} {{ $event['target_hp'] }} {{ __('health') }})
                            </span>
                        </p>
                    @endforeach
                </div>

                <img class="my-5 mx-auto" src="{{ asset('images/border2.png') }}" alt="">

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
