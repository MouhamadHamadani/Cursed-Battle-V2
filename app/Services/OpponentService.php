<?php

namespace App\Services;

use App\Models\Character;
use Illuminate\Support\Facades\DB;

/**
 * Opponent search: one mark revealed at a time, held on the character row.
 *
 * The first reveal is free. Rejecting the revealed mark for another is a
 * re-roll, and re-rolls get steadily more expensive until the character
 * commits to a fight — CombatService zeroes the counter when it does.
 *
 * State lives in `characters.opponent_id` / `.opponent_rerolls` rather than in
 * the session or the Livewire component so a refresh, a second tab or a logout
 * cannot be used to dodge the price.
 */
class OpponentService
{
    /** Gold for the first re-roll. Doubles per re-roll already bought: 10, 20, 40, 80... */
    public const REROLL_BASE = 10;

    /**
     * Exponent ceiling for the doubling curve.
     *
     * ponytail: flat past this point (10 * 2^16 = 655,360 gold, far beyond
     * what any character can pay) purely to keep the arithmetic bounded. If
     * the economy ever grows into that range, replace the curve rather than
     * raising the cap.
     */
    public const REROLL_EXPONENT_CAP = 16;

    /**
     * The mark currently revealed, or null if there is none.
     *
     * A reveal the game itself invalidated — the mark got hospitalized after
     * being revealed — is cleared here, which makes the replacement free. The
     * player paid for a usable target and did not get to use it; charging
     * again for the game's own doing would be a bug, not a balance choice.
     */
    public function current(Character $character): ?Character
    {
        if ($character->opponent_id === null) {
            return null;
        }

        $opponent = Character::with('user')->find($character->opponent_id);

        if ($opponent === null || $opponent->isHospitalized()) {
            $character->update(['opponent_id' => null]);

            return null;
        }

        return $opponent;
    }

    /**
     * What the next find() will charge: nothing when no mark is revealed,
     * an escalating price when one is being thrown away.
     */
    public function cost(Character $character): int
    {
        return $this->current($character) === null ? 0 : $this->rerollCost($character->opponent_rerolls);
    }

    /**
     * Reveal an opponent, charging for it if this is a re-roll. Multi-write
     * (gold + counter + the reveal itself) so it runs inside a transaction
     * with a row lock on the character, same shape as MarketService::buy().
     *
     * @throws GameActionException
     */
    public function find(Character $character): Character
    {
        app(ActivityService::class)->resolvePending($character);

        return DB::transaction(function () use ($character) {
            $fresh = Character::whereKey($character->id)->lockForUpdate()->firstOrFail();

            // Guarded so gold is never taken for a hunt the character cannot
            // act on — both states block attacking outright (ADR-001, ADR-002).
            if ($fresh->isHospitalized()) {
                throw new GameActionException('You are hospitalized and cannot fight.');
            }

            if ($fresh->isBusy()) {
                throw new GameActionException('Thou canst not hunt for a mark while '.ActivityService::describe($fresh->activity_type).'.');
            }

            $rejectedId = $this->current($fresh)?->id;
            $cost = $rejectedId === null ? 0 : $this->rerollCost($fresh->opponent_rerolls);

            if ($fresh->gold < $cost) {
                throw new GameActionException('Not enough gold to seek another.');
            }

            $opponent = Character::with('user')
                ->whereKeyNot($fresh->id)
                // A paid re-roll that hands back the same face is not a re-roll.
                ->when($rejectedId, fn ($query) => $query->whereKeyNot($rejectedId))
                ->where(fn ($query) => $query->whereNull('hospitalized_until')->orWhere('hospitalized_until', '<=', now()))
                ->inRandomOrder()
                ->first();

            // Thrown before any write, so a fruitless search costs nothing.
            if ($opponent === null) {
                throw new GameActionException('There is no one else to face.');
            }

            $fresh->gold -= $cost;
            $fresh->opponent_id = $opponent->id;

            if ($rejectedId !== null) {
                $fresh->opponent_rerolls++;
            }

            $fresh->save();

            return $opponent;
        });
    }

    /**
     * Drop the reveal and put the price back to free. Called when a fight is
     * committed (CombatService), win or lose.
     */
    public function clear(Character $character): void
    {
        $character->update(['opponent_id' => null, 'opponent_rerolls' => 0]);
    }

    private function rerollCost(int $rerollsAlreadyBought): int
    {
        return self::REROLL_BASE * (2 ** min($rerollsAlreadyBought, self::REROLL_EXPONENT_CAP));
    }
}
