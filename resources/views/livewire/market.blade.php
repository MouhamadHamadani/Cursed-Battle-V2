<div>
    <x-slot name="header">
        <x-label class="font-uncialAntiqua text-4xl sm:text-6xl text-yellow-500 text-shadow-lg shadow-yellow-600">
            {{ __('Market') }}
        </x-label>
    </x-slot>

    @php
        // Stat deltas kept as values, not a pre-joined string, so each can be
        // coloured green/red the way V1 does. This is the POPUP's view of an
        // item — all four, zeroes included. Cards get $headlineOf instead.
        $deltasOf = fn ($item) => [
            ['label' => 'STR', 'value' => $item->strength_delta],
            ['label' => 'DEF', 'value' => $item->defense_delta],
            ['label' => 'SPD', 'value' => $item->speed_delta],
            ['label' => 'DEX', 'value' => $item->dexterity_delta],
        ];

        // Green up, red down, dim for a stat the item does not touch.
        $deltaColour = fn ($value) => $value > 0
            ? 'text-green-500'
            : ($value < 0 ? 'text-red-500' : 'text-stone-500');

        // The card's single headline stat: the largest-magnitude non-zero
        // delta. $deltasOf is already in priority order (STR > DEF > SPD > DEX)
        // and the comparison is strict, so a magnitude tie keeps the
        // higher-priority stat. Null when an item changes nothing.
        $headlineOf = function ($item) use ($deltasOf) {
            $best = null;
            foreach ($deltasOf($item) as $delta) {
                if ($delta['value'] !== 0 && ($best === null || abs($delta['value']) > abs($best['value']))) {
                    $best = $delta;
                }
            }

            return $best;
        };

        // Items carry no art yet (image column is nullable) — fall back to the crest.
        $artOf = fn ($item) => $item->image && file_exists(public_path($item->image))
            ? asset($item->image)
            : asset('images/logo 2.png');

        $iconOf = fn (string $slot) => match ($slot) {
            'shield' => 'fa-shield-halved',
            'head' => 'fa-helmet-battle',
            'body' => 'fa-vest',
            default => 'fa-swords',
        };

        // `body` is a full worn suit, so it reads as "Armor" to a player — the
        // column name is mechanical, this is the label.
        $slotLabel = fn (string $slot) => match ($slot) {
            'weapon' => __('Weapon'),
            'shield' => __('Shield'),
            'head' => __('Head'),
            'body' => __('Armor'),
            default => ucfirst($slot),
        };
    @endphp

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <x-flash />

            {{-- Shop --}}
            <div>
                <x-label class="font-uncialAntiqua text-3xl text-yellow-500 text-center mb-6">
                    <i class="fa-duotone fa-solid fa-treasure-chest me-2"></i>{{ __('Shop') }}
                </x-label>

                {{-- One slot at a time, V1's category-tile pattern. Active tab is
                     the primary button, the rest are secondary — no class
                     wrestling, just the two buttons the kit already has. --}}
                <div class="flex flex-wrap justify-center gap-3 mb-6">
                    @foreach (\App\Models\Item::SLOTS as $slot)
                        @if ($this->shopSlot === $slot)
                            <x-button type="button" wire:click="selectSlot('{{ $slot }}')" wire:loading.attr="disabled">
                                <i class="fa-duotone fa-solid {{ $iconOf($slot) }} me-2"></i>{{ $slotLabel($slot) }}
                            </x-button>
                        @else
                            <x-secondary-button wire:click="selectSlot('{{ $slot }}')" wire:loading.attr="disabled">
                                <i class="fa-duotone fa-solid {{ $iconOf($slot) }} me-2"></i>{{ $slotLabel($slot) }}
                            </x-secondary-button>
                        @endif
                    @endforeach
                </div>

                <div class="flex flex-wrap justify-center gap-6">
                    @forelse ($this->shopItems as $item)
                        @php
                            $owned = in_array($item->id, $this->ownedItemIds, true);
                            $locked = $this->character->level < $item->min_level;
                            $affordable = $this->character->gold >= $item->cost;
                        @endphp

                        {{-- wire:key moves out to the wrapper with the flex basis:
                             it has to sit on the loop iteration's root element,
                             and the scrollwork is now that element. --}}
                        <x-iron-scrollwork wire:key="shop-{{ $item->id }}"
                                           class="grid basis-full sm:basis-[calc(50%-0.75rem)] lg:basis-[calc(33.333%-1rem)]">
                            <x-item-card :item="$item"
                                         :art="$artOf($item)"
                                         :icon="$iconOf($item->slot)"
                                         :slot-label="$slotLabel($item->slot)"
                                         :headline="$headlineOf($item)"
                                         :affordable="$affordable">
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
                            </x-item-card>
                        </x-iron-scrollwork>
                    @empty
                        <x-label class="text-stone-400">
                            {{ __('No :slot wares are offered to you yet.', ['slot' => strtolower($slotLabel($this->shopSlot))]) }}
                        </x-label>
                    @endforelse
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
                        @php $item = $characterItem->item; @endphp

                        <x-iron-scrollwork wire:key="inv-{{ $characterItem->id }}"
                                           class="grid basis-full sm:basis-[calc(50%-0.75rem)] lg:basis-[calc(33.333%-1rem)]">
                            {{-- Same card as the Shop: an owned item gets no
                                 richer treatment just because the list is shorter. --}}
                            <x-item-card :item="$item"
                                         :art="$artOf($item)"
                                         :icon="$iconOf($item->slot)"
                                         :slot-label="$slotLabel($item->slot)"
                                         :headline="$headlineOf($item)"
                                         :equipped="$characterItem->equipped">
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
                            </x-item-card>
                        </x-iron-scrollwork>
                    @empty
                        <x-label class="text-stone-400">{{ __('You have no items yet.') }}</x-label>
                    @endforelse
                </div>
            </div>

        </div>
    </div>

    {{-- "More details" popup — the full tier. Everything the card omits lives
         here: all four deltas, the flavour text, and a spelled-out slot.
         Same x-dark-modal + entangled wire:model.live pattern as the faction
         panel on Home and the delete-account form. --}}
    <x-dark-modal wire:model.live="showDetails" maxWidth="lg">
        @if ($this->selectedItem)
            @php
                $detail = $this->selectedItem;
                $ownedRow = $this->selectedOwned;
                $detailLocked = $this->character->level < $detail->min_level;
                $detailAffordable = $this->character->gold >= $detail->cost;
            @endphp

            <div class="p-8 text-center">
                <img class="h-56 w-full object-contain" src="{{ $artOf($detail) }}" alt="{{ $detail->name }}">

                <x-label class="font-uncialAntiqua text-3xl text-yellow-500 mt-4">{{ $detail->name }}</x-label>
                <x-label class="block text-xs uppercase tracking-widest text-stone-400 mt-1">
                    <i class="fa-duotone fa-solid {{ $iconOf($detail->slot) }} me-1"></i>{{ $slotLabel($detail->slot) }}
                </x-label>

                <x-chain-divider class="my-5" />

                @if ($detail->description)
                    <p class="font-sans text-sm text-stone-300">{{ $detail->description }}</p>
                @endif

                {{-- All four stats, zeroes included: this is the full breakdown,
                     not the card's at-a-glance headline. --}}
                <div class="grid grid-cols-4 gap-2 mt-6">
                    @foreach ($deltasOf($detail) as $delta)
                        <div>
                            <x-label class="text-xs text-stone-400">{{ $delta['label'] }}</x-label>
                            <x-label class="text-2xl {{ $deltaColour($delta['value']) }}">
                                {{ $delta['value'] > 0 ? '+' : '' }}{{ $delta['value'] }}
                            </x-label>
                        </div>
                    @endforeach
                </div>

                <div class="flex justify-center gap-8 mt-6">
                    <div>
                        <x-label class="text-xs text-stone-400">{{ __('Req. level') }}</x-label>
                        <x-label class="block text-xl {{ $detailLocked ? 'text-red-500' : '' }}">{{ $detail->min_level }}</x-label>
                    </div>
                    <div>
                        <x-label class="text-xs text-stone-400">{{ __('Cost') }}</x-label>
                        <x-label class="block text-xl">
                            <i class="fa-duotone fa-solid fa-coins text-yellow-500 me-1"></i>
                            <span class="{{ $ownedRow || $detailAffordable ? '' : 'text-red-500' }}">{{ $detail->cost }}</span>
                        </x-label>
                    </div>
                </div>

                {{-- One action, matching whichever applies right now, so acting
                     never means closing the popup first. --}}
                <div class="mt-8">
                    @if ($ownedRow && $ownedRow->equipped)
                        <x-secondary-button class="w-full" wire:click="unequip({{ $detail->id }})"
                                            wire:target="unequip" wire:loading.attr="disabled">
                            {{ __('Unequip') }}
                        </x-secondary-button>
                    @elseif ($ownedRow)
                        <x-button class="w-full" target="equip" wire:click="equip({{ $detail->id }})" wire:loading.attr="disabled">
                            {{ __('Equip') }}
                        </x-button>
                    @elseif ($detailLocked)
                        <x-button class="w-full" disable>
                            <i class="fa-duotone fa-solid fa-lock me-2"></i>{{ __('Locked (Lv :level)', ['level' => $detail->min_level]) }}
                        </x-button>
                    @elseif (! $detailAffordable)
                        <x-button class="w-full" disable>{{ __("Can't afford") }}</x-button>
                    @else
                        <x-button class="w-full" target="buy" wire:click="buy({{ $detail->id }})" wire:loading.attr="disabled">
                            {{ __('Buy') }}
                        </x-button>
                    @endif
                </div>

                <div class="flex justify-center mt-6">
                    <x-button type="button" x-on:click="show = false">{{ __('Close') }}</x-button>
                </div>
            </div>
        @endif
    </x-dark-modal>
</div>
