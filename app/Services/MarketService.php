<?php

namespace App\Services;

use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\Item;
use Illuminate\Support\Facades\DB;

class MarketService
{
    /**
     * The market is part of ADR-002's full lock: a character mid Train/Work
     * session cannot trade. Callers resolve any due session first, so this
     * only fires for a session that is genuinely still running.
     *
     * @throws GameActionException
     */
    private static function assertNotBusy(Character $character): void
    {
        if ($character->isBusy()) {
            throw new GameActionException('The merchants will not serve thee while thou art '.ActivityService::describe($character->activity_type).'.');
        }
    }

    /**
     * Buy an item: level-gated, one-per-item, gold-gated. Multi-write
     * (decrement gold + insert pivot) so it runs inside a transaction with a
     * row lock on the character (laravel 12.x/queries Pessimistic Locking) —
     * a thrown exception rolls the whole transaction back.
     */
    public function buy(Character $character, Item $item): CharacterItem
    {
        app(ActivityService::class)->resolvePending($character);

        return DB::transaction(function () use ($character, $item) {
            $fresh = Character::whereKey($character->id)->lockForUpdate()->firstOrFail();

            self::assertNotBusy($fresh);

            // Faction gate: a NULL item faction is universal, anything else is
            // sold only to its own. Enforced here and not just in the listing —
            // $item comes from untrusted client input.
            if ($item->faction !== null && $item->faction !== $fresh->faction) {
                throw new GameActionException('These wares are not sold to your kind.');
            }

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
     * Equip an owned item. Multi-write (unequip the current occupant of this
     * item's slot + equip this one) so it runs inside a transaction.
     *
     * Keyed on `slot` since ADR-003, which is the whole cost of going from
     * two slots to four — the one-per-slot rule generalises for free.
     */
    public function equip(Character $character, Item $item): void
    {
        app(ActivityService::class)->resolvePending($character);
        self::assertNotBusy($character->refresh());

        DB::transaction(function () use ($character, $item) {
            // Lock the character row before reading, so a whole slot swap
            // serializes against a concurrent one. Without it two swaps in the
            // same slot can interleave and leave the slot empty.
            Character::whereKey($character->id)->lockForUpdate()->firstOrFail();

            $owned = CharacterItem::where('character_id', $character->id)->where('item_id', $item->id)->first();
            if (! $owned) {
                throw new GameActionException('You do not own this item.');
            }

            // one equipped per slot: unequip the current occupant first
            CharacterItem::where('character_id', $character->id)
                ->whereHas('item', fn ($q) => $q->where('slot', $item->slot))
                ->update(['equipped' => false]);

            // Query builder, NOT $owned->update(): the mass-unequip above
            // includes this very row, but only in the database — the loaded
            // model still holds equipped => true, so Eloquent would find
            // nothing dirty (the boolean cast makes true and 1 equivalent),
            // skip the UPDATE entirely, and re-equipping an already-equipped
            // item would silently strip it. Do not "fix" this by refreshing
            // the model first; that re-arms the same trap for the next edit.
            CharacterItem::whereKey($owned->id)->update(['equipped' => true]);
        });
    }

    /**
     * Unequip an owned item.
     */
    public function unequip(Character $character, Item $item): void
    {
        app(ActivityService::class)->resolvePending($character);
        self::assertNotBusy($character->refresh());

        CharacterItem::where('character_id', $character->id)->where('item_id', $item->id)
            ->update(['equipped' => false]);
    }
}
