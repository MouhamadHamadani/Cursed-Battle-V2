@props([
    'item',
    'art',
    'icon',
    'slotLabel',
    'headline' => null,
    'equipped' => false,
    'affordable' => true,
    'closed' => false,
])

{{--
    One card for both the Shop and the Inventory grid — the two were duplicated
    markup before this partial existed.

    The card tier is deliberately thin: art, name, ONE headline stat, level,
    cost, and a single action. The full four-stat breakdown and the flavour text
    live in the details popup instead — at four stats (and with shields carrying
    a deliberate positive/negative split) a card that shows everything starts
    competing with its own action button for space.
--}}
{{--
    `closed` is the page-wide lock (a Work/Train session in progress, which
    MarketService refuses every action under). It drops the hover promise the
    way Home demotes a blocked quick link. Equipped keeps its green even while
    closed: that is a status, not an affordance, and losing it would hide which
    item is actually worn.

    Only the page-wide lock demotes. A locked-by-level or unaffordable card
    keeps its gold border on purpose — those are normal, expected states while
    browsing a shop, and the card already says so in red on the cost and in the
    button. Dimming a third of the catalogue permanently reads as broken.
--}}
<x-dark-wall class="relative flex flex-col border p-5 transition duration-300 {{ $equipped ? 'border-green-500' : ($closed ? 'border-stone-700' : 'border-yellow-700 hover:border-yellow-500') }}">
    {{-- The card art is the "more details" entry point; the action button below
         stays the fast path. --}}
    <button type="button" class="group relative block w-full"
            wire:click="selectItem({{ $item->id }})"
            aria-label="{{ __('Details for :name', ['name' => $item->name]) }}">
        <img class="h-40 w-full object-contain" src="{{ $art }}" alt="{{ $item->name }}">
        <i class="fa-duotone fa-solid fa-circle-info absolute top-0 end-0 text-yellow-700 group-hover:text-yellow-500 transition"></i>
    </button>

    <div class="flex items-center justify-center gap-2 mt-3">
        {{-- Slot as a bare icon rather than a text line: the Shop is browsed one
             slot at a time, and in the mixed Inventory list the icon is enough
             to tell a helm from a shield at a glance. --}}
        <i class="fa-duotone fa-solid {{ $icon }} text-stone-400" title="{{ $slotLabel }}"></i>
        <x-label class="text-xl">{{ $item->name }}</x-label>
        @if ($equipped)
            <x-label class="text-xs uppercase tracking-widest text-green-500">{{ __('Equipped') }}</x-label>
        @endif
    </div>

    {{-- At most one stat: the largest-magnitude non-zero delta. A shield reads
         "+3 DEF" here and discloses its speed/dexterity cost in the popup. --}}
    @if ($headline)
        <x-label class="text-lg text-center mt-2 {{ $headline['value'] > 0 ? 'text-green-500' : 'text-red-500' }}">
            {{ $headline['value'] > 0 ? '+' : '' }}{{ $headline['value'] }} {{ $headline['label'] }}
        </x-label>
    @endif

    <div class="mt-3 space-y-1 text-center">
        <x-label class="text-sm text-stone-400">{{ __('Req. level') }} {{ $item->min_level }}</x-label>
        <x-label class="text-sm">
            <i class="fa-duotone fa-solid fa-coins text-yellow-500 me-1"></i>
            <span class="{{ $affordable ? 'text-white' : 'text-red-500' }}">{{ $item->cost }}</span>
        </x-label>
    </div>

    {{-- Whichever action applies — the caller owns that decision. --}}
    <div class="mt-auto pt-5">{{ $slot }}</div>
</x-dark-wall>
