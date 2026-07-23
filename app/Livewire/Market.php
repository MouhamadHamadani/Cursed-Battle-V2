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
    #[Computed]
    public function character()
    {
        return auth()->user()->character;
    }

    #[Computed]
    public function items()
    {
        return Item::orderBy('min_level')->orderBy('cost')->get();
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
            unset($this->character, $this->ownedItemIds, $this->inventory);
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
            unset($this->character, $this->inventory);
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
            unset($this->character, $this->inventory);
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
