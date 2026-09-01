<div>
    <x-slot name="header">
        <x-label class="font-uncialAntiqua text-4xl sm:text-6xl text-yellow-500 text-shadow-lg shadow-yellow-600">
            {{ __('Market') }}
        </x-label>
    </x-slot>

    @php
        // Stat deltas kept as values, not a pre-joined string, so each can be
        // coloured green/red the way V1 does.
        $deltasOf = fn ($item) => array_values(array_filter([
            ['label' => 'STR', 'value' => $item->strength_delta],
            ['label' => 'DEF', 'value' => $item->defense_delta],
            ['label' => 'SPD', 'value' => $item->speed_delta],
            ['label' => 'DEX', 'value' => $item->dexterity_delta],
        ], fn ($d) => $d['value'] !== 0));

        // Items carry no art yet (image column is nullable) — fall back to the crest.
        $artOf = fn ($item) => $item->image && file_exists(public_path($item->image))
            ? asset($item->image)
            : asset('images/logo 2.png');

        $iconOf = fn ($item) => $item->type === 'armor' ? 'fa-shield-halved' : 'fa-swords';
    @endphp

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <x-flash />

            {{-- Shop --}}
            <div>
                <x-label class="font-uncialAntiqua text-3xl text-yellow-500 text-center mb-6">
                    <i class="fa-duotone fa-solid fa-treasure-chest me-2"></i>{{ __('Shop') }}
                </x-label>

                <div class="flex flex-wrap justify-center gap-6">
                    @foreach ($this->items as $item)
                        @php
                            $deltas = $deltasOf($item);
                            $owned = in_array($item->id, $this->ownedItemIds, true);
                            $locked = $this->character->level < $item->min_level;
                            $affordable = $this->character->gold >= $item->cost;
                        @endphp

                        {{-- wire:key moves out to the wrapper with the flex basis:
                             it has to sit on the loop iteration's root element,
                             and the scrollwork is now that element. --}}
                        <x-iron-scrollwork wire:key="shop-{{ $item->id }}"
                                           class="grid basis-full sm:basis-[calc(50%-0.75rem)] lg:basis-[calc(33.333%-1rem)]">
                        <x-dark-wall class="flex flex-col border border-yellow-700 p-5 hover:border-yellow-500 transition duration-300">
                            <img class="h-40 w-full object-contain" src="{{ $artOf($item) }}" alt="{{ $item->name }}">

                            <x-label class="text-xl text-center mt-3">{{ $item->name }}</x-label>
                            <x-label class="text-xs text-center uppercase tracking-widest text-stone-400 mt-1">
                                <i class="fa-duotone fa-solid {{ $iconOf($item) }} me-1"></i>{{ $item->type }}
                            </x-label>

                            {{-- Flavour only — no mechanical effect, and nullable. --}}
                            @if ($item->description)
                                <p class="font-sans text-xs text-center italic text-stone-400 mt-2">{{ $item->description }}</p>
                            @endif

                            @if (count($deltas))
                                <div class="flex flex-wrap justify-center gap-x-4 gap-y-1 mt-3">
                                    @foreach ($deltas as $delta)
                                        <x-label class="text-sm">
                                            <strong class="text-stone-400">{{ $delta['label'] }}:</strong>
                                            <span class="{{ $delta['value'] > 0 ? 'text-green-500' : 'text-red-500' }}">
                                                {{ $delta['value'] > 0 ? '+' : '' }}{{ $delta['value'] }}
                                            </span>
                                        </x-label>
                                    @endforeach
                                </div>
                            @endif

                            <div class="mt-3 space-y-1 text-center">
                                <x-label class="text-sm text-stone-400">{{ __('Req. level') }} {{ $item->min_level }}</x-label>
                                <x-label class="text-sm">
                                    <i class="fa-duotone fa-solid fa-coins text-yellow-500 me-1"></i>
                                    <span class="{{ $affordable ? 'text-white' : 'text-red-500' }}">{{ $item->cost }}</span>
                                </x-label>
                            </div>

                            <div class="mt-auto pt-5">
                                @if ($owned)
                                    <x-button class="w-full" disable>
                                        <i class="fa-duotone fa-solid fa-circle-check me-2"></i>{{ __('Owned') }}
                                    </x-button>
                                @elseif ($locked)
                                    <x-button class="w-full" disable>
                                        <i class="fa-duotone fa-solid fa-lock me-2"></i>{{ __('Locked (Lv :level)', ['level' => $item->min_level]) }}
                                    </x-button>
                                @elseif (! $affordable)
                                    <x-button class="w-full" disable>{{ __("Can't afford") }}</x-button>
                                @else
                                    <x-button
                                        class="w-full"
                                        target="buy"
                                        wire:click="buy({{ $item->id }})"
                                        wire:loading.attr="disabled"
                                    >
                                        {{ __('Buy') }}
                                    </x-button>
                                @endif
                            </div>
                        </x-dark-wall>
                        </x-iron-scrollwork>
                    @endforeach
                </div>
            </div>

            <x-divider />

            {{-- Inventory --}}
            <div>
                <x-label class="font-uncialAntiqua text-3xl text-yellow-500 text-center mb-6">
                    <i class="fa-duotone fa-solid fa-sack me-2"></i>{{ __('Inventory') }}
                </x-label>

                <div class="flex flex-wrap justify-center gap-6">
                    @forelse ($this->inventory as $characterItem)
                        @php
                            $item = $characterItem->item;
                            $deltas = $deltasOf($item);
                        @endphp

                        <x-iron-scrollwork wire:key="inv-{{ $characterItem->id }}"
                                           class="grid basis-full sm:basis-[calc(50%-0.75rem)] lg:basis-[calc(33.333%-1rem)]">
                        <x-dark-wall class="flex flex-col border {{ $characterItem->equipped ? 'border-green-500' : 'border-yellow-700' }} p-5 transition duration-300">
                            <img class="h-40 w-full object-contain" src="{{ $artOf($item) }}" alt="{{ $item->name }}">

                            <div class="flex items-center justify-center gap-2 mt-3">
                                <x-label class="text-xl">{{ $item->name }}</x-label>
                                @if ($characterItem->equipped)
                                    <x-label class="text-xs uppercase tracking-widest text-green-500">{{ __('Equipped') }}</x-label>
                                @endif
                            </div>

                            <x-label class="text-xs text-center uppercase tracking-widest text-stone-400 mt-1">
                                <i class="fa-duotone fa-solid {{ $iconOf($item) }} me-1"></i>{{ $item->type }}
                            </x-label>

                            {{-- Flavour only — no mechanical effect, and nullable. --}}
                            @if ($item->description)
                                <p class="font-sans text-xs text-center italic text-stone-400 mt-2">{{ $item->description }}</p>
                            @endif

                            @if (count($deltas))
                                <div class="flex flex-wrap justify-center gap-x-4 gap-y-1 mt-3">
                                    @foreach ($deltas as $delta)
                                        <x-label class="text-sm">
                                            <strong class="text-stone-400">{{ $delta['label'] }}:</strong>
                                            <span class="{{ $delta['value'] > 0 ? 'text-green-500' : 'text-red-500' }}">
                                                {{ $delta['value'] > 0 ? '+' : '' }}{{ $delta['value'] }}
                                            </span>
                                        </x-label>
                                    @endforeach
                                </div>
                            @endif

                            <div class="mt-auto pt-5">
                                @if ($characterItem->equipped)
                                    <x-secondary-button
                                        class="w-full"
                                        wire:click="unequip({{ $characterItem->item_id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="unequip"
                                    >
                                        {{ __('Unequip') }}
                                    </x-secondary-button>
                                @else
                                    <x-button
                                        class="w-full"
                                        target="equip"
                                        wire:click="equip({{ $characterItem->item_id }})"
                                        wire:loading.attr="disabled"
                                    >
                                        {{ __('Equip') }}
                                    </x-button>
                                @endif
                            </div>
                        </x-dark-wall>
                        </x-iron-scrollwork>
                    @empty
                        <x-label class="text-stone-400">{{ __('You have no items yet.') }}</x-label>
                    @endforelse
                </div>
            </div>

        </div>
    </div>
</div>
