<?php

namespace App\Services;

use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\Item;
use Illuminate\Support\Facades\DB;

class MarketService
{
    /**
     * Buy an item: level-gated, one-per-item, gold-gated. Multi-write
     * (decrement gold + insert pivot) so it runs inside a transaction with a
     * row lock on the character (laravel 12.x/queries Pessimistic Locking) —
     * a thrown exception rolls the whole transaction back.
     */
    public function buy(Character $character, Item $item): CharacterItem
    {
        return DB::transaction(function () use ($character, $item) {
            $fresh = Character::whereKey($character->id)->lockForUpdate()->firstOrFail();

            if ($fresh->level < $item->min_level) {
                throw new GameActionException('Your level is too low for this item.');
            }

            if (CharacterItem::where('character_id', $fresh->id)->where('item_id', $item->id)->exists()) {
                throw new GameActionException('You already own this item.');
            }

            if ($fresh->gold < $item->cost) {
                throw new GameActionException('Not enough gold.');
            }

            $fresh->decrement('gold', $item->cost);

            return CharacterItem::create([
                'character_id' => $fresh->id,
                'item_id' => $item->id,
                'equipped' => false,
            ]);
        });
    }

    /**
     * Equip an owned item. Multi-write (unequip current same-type item(s) +
     * equip this one) so it runs inside a transaction.
     */
    public function equip(Character $character, Item $item): void
    {
        DB::transaction(function () use ($character, $item) {
            $owned = CharacterItem::where('character_id', $character->id)->where('item_id', $item->id)->first();
            if (! $owned) {
                throw new GameActionException('You do not own this item.');
            }

            // one equipped per type: unequip current same-type item(s) first
            CharacterItem::where('character_id', $character->id)
                ->whereHas('item', fn ($q) => $q->where('type', $item->type))
                ->update(['equipped' => false]);

            $owned->update(['equipped' => true]);
        });
    }

    /**
     * Unequip an owned item.
     */
    public function unequip(Character $character, Item $item): void
    {
        CharacterItem::where('character_id', $character->id)->where('item_id', $item->id)
            ->update(['equipped' => false]);
    }
}
