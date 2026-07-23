<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Market') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-700 text-green-700 dark:text-green-200 px-4 py-3 rounded-lg">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('error'))
                <div class="bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-200 px-4 py-3 rounded-lg">
                    {{ session('error') }}
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Gold') }}</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $this->character->gold }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Level') }}</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $this->character->level }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-3">{{ __('Shop') }}</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    @foreach ($this->items as $item)
                        @php
                            $deltas = [];
                            if ($item->strength_delta !== 0) { $deltas[] = ($item->strength_delta > 0 ? '+' : '').$item->strength_delta.' STR'; }
                            if ($item->defense_delta !== 0) { $deltas[] = ($item->defense_delta > 0 ? '+' : '').$item->defense_delta.' DEF'; }
                            if ($item->agility_delta !== 0) { $deltas[] = ($item->agility_delta > 0 ? '+' : '').$item->agility_delta.' AGI'; }

                            $owned = in_array($item->id, $this->ownedItemIds, true);
                            $locked = $this->character->level < $item->min_level;
                            $affordable = $this->character->gold >= $item->cost;
                        @endphp

                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6 text-gray-900 dark:text-gray-100 space-y-3">
                                <h4 class="text-lg font-semibold">{{ $item->name }}</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400 uppercase tracking-wide">{{ $item->type }}</p>

                                @if (count($deltas))
                                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ implode(', ', $deltas) }}</p>
                                @endif

                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Req. level') }} {{ $item->min_level }}</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $item->cost }} {{ __('gold') }}</p>

                                @if ($owned)
                                    <span class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-gray-500 dark:text-gray-400 uppercase tracking-widest cursor-not-allowed">
                                        {{ __('Owned') }}
                                    </span>
                                @elseif ($locked)
                                    <span class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-gray-500 dark:text-gray-400 uppercase tracking-widest cursor-not-allowed">
                                        {{ __('Locked (Lv :level)', ['level' => $item->min_level]) }}
                                    </span>
                                @elseif (! $affordable)
                                    <x-primary-button disabled class="opacity-50 cursor-not-allowed">
                                        {{ __("Can't afford") }}
                                    </x-primary-button>
                                @else
                                    <x-primary-button
                                        wire:click="buy({{ $item->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="buy"
                                    >
                                        {{ __('Buy') }}
                                    </x-primary-button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-3">{{ __('Inventory') }}</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    @forelse ($this->inventory as $characterItem)
                        @php
                            $item = $characterItem->item;
                            $deltas = [];
                            if ($item->strength_delta !== 0) { $deltas[] = ($item->strength_delta > 0 ? '+' : '').$item->strength_delta.' STR'; }
                            if ($item->defense_delta !== 0) { $deltas[] = ($item->defense_delta > 0 ? '+' : '').$item->defense_delta.' DEF'; }
                            if ($item->agility_delta !== 0) { $deltas[] = ($item->agility_delta > 0 ? '+' : '').$item->agility_delta.' AGI'; }
                        @endphp

                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6 text-gray-900 dark:text-gray-100 space-y-3">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-lg font-semibold">{{ $item->name }}</h4>
                                    @if ($characterItem->equipped)
                                        <span class="inline-flex items-center px-2 py-1 bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-200 rounded text-xs font-semibold uppercase tracking-widest">
                                            {{ __('Equipped') }}
                                        </span>
                                    @endif
                                </div>
                                <p class="text-sm text-gray-500 dark:text-gray-400 uppercase tracking-wide">{{ $item->type }}</p>

                                @if (count($deltas))
                                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ implode(', ', $deltas) }}</p>
                                @endif

                                @if ($characterItem->equipped)
                                    <x-secondary-button
                                        wire:click="unequip({{ $characterItem->item_id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="unequip"
                                    >
                                        {{ __('Unequip') }}
                                    </x-secondary-button>
                                @else
                                    <x-primary-button
                                        wire:click="equip({{ $characterItem->item_id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="equip"
                                    >
                                        {{ __('Equip') }}
                                    </x-primary-button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('You have no items yet.') }}</p>
                    @endforelse
                </div>
            </div>

        </div>
    </div>
</div>
