<?php

namespace App\Livewire;

use App\Models\Item;
use App\Services\GameActionException;
use App\Services\MarketService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Market extends Component
{
    /**
     * The "more details" popup. The on-card Buy button stays the fast path;
     * this is an additional entry point, not a replacement for it.
     */
    public bool $showDetails = false;

    public ?int $selectedItemId = null;

    /**
     * The Shop is browsed one slot at a time (V1's category tiles). This is
     * what lets a card drop its slot text line — you already know what you're
     * looking at. The Inventory stays a single mixed list.
     */
    public string $shopSlot = 'weapon';

    #[Computed]
    public function character()
    {
        return auth()->user()->character;
    }

    /**
     * Switch the Shop tab. $slot is untrusted client input, so it is checked
     * against the canonical list rather than trusted into the filter.
     */
    public function selectSlot(string $slot): void
    {
        if (in_array($slot, Item::SLOTS, true)) {
            $this->shopSlot = $slot;
            unset($this->shopItems);
        }
    }

    /**
     * The Shop grid: the listable catalogue narrowed to the active tab.
     * Deliberately separate from items() — selectItem() and the details popup
     * still resolve against the full listable set, so an item stays
     * inspectable regardless of which tab is open.
     */
    #[Computed]
    public function shopItems()
    {
        return $this->items->where('slot', $this->shopSlot)->values();
    }

    /**
     * Open the details popup for an item. $id is untrusted client input, but
     * this only ever reads a public catalogue row — and the listing scope is
     * re-applied so a faction-locked item can't be inspected by outsiders.
     */
    public function selectItem(int $id): void
    {
        $this->selectedItemId = $this->items->firstWhere('id', $id)?->id;
        $this->showDetails = $this->selectedItemId !== null;
    }

    #[Computed]
    public function selectedItem(): ?Item
    {
        return $this->selectedItemId === null
            ? null
            : $this->items->firstWhere('id', $this->selectedItemId);
    }

    /** The owned row for the selected item, or null if it isn't owned yet. */
    #[Computed]
    public function selectedOwned()
    {
        return $this->selectedItemId === null
            ? null
            : $this->inventory->firstWhere('item_id', $this->selectedItemId);
    }

    #[Computed]
    public function items()
    {
        // Faction-locked items are hidden from other factions; NULL is universal.
        return Item::where(fn ($q) => $q->whereNull('faction')->orWhere('faction', $this->character->faction))
            ->orderBy('min_level')
            ->orderBy('cost')
            ->get();
    }

    #[Computed]
    public function ownedItemIds()
    {
        return $this->character->characterItems()->pluck('item_id')->all();
    }

    #[Computed]
    public function inventory()
    {
        return $this->character->characterItems()->with('item')->get();
    }

    /**
     * Buy the given item. All game rules (level gate, ownership, affordability)
     * are re-validated server-side inside MarketService — $itemId is untrusted
     * client input; the character is always the authed user's own.
     */
    public function buy(int $itemId): void
    {
        $item = Item::findOrFail($itemId);

        try {
            app(MarketService::class)->buy($this->character, $item);
            unset($this->character, $this->ownedItemIds, $this->inventory, $this->selectedOwned);
            $this->dispatch('character-updated');
            session()->flash('status', "Purchased {$item->name}.");
        } catch (GameActionException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    /**
     * Equip an owned item. Ownership is re-validated server-side inside
     * MarketService — $itemId is untrusted client input.
     */
    public function equip(int $itemId): void
    {
        $item = Item::findOrFail($itemId);

        try {
            app(MarketService::class)->equip($this->character, $item);
            unset($this->character, $this->inventory, $this->selectedOwned);
            $this->dispatch('character-updated');
            session()->flash('status', "Equipped {$item->name}.");
        } catch (GameActionException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    /**
     * Unequip an owned item.
     */
    public function unequip(int $itemId): void
    {
        $item = Item::findOrFail($itemId);

        try {
            app(MarketService::class)->unequip($this->character, $item);
            unset($this->character, $this->inventory, $this->selectedOwned);
            $this->dispatch('character-updated');
            session()->flash('status', "Unequipped {$item->name}.");
        } catch (GameActionException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.market');
    }
}
