<?php

namespace App\Services;

use App\Models\Character;
use App\Models\Occupation;
use Illuminate\Support\Facades\DB;

class WorkService
{
    /**
     * Spend a character's entire current energy working an occupation,
     * earning energy_spent * occupation.gold_per_energy gold.
     *
     * @return array{energy_spent: int, gold_earned: int, character: Character}
     */
    public function work(Character $character, Occupation $occupation): array
    {
        // Level gate: max_level null = no upper cap.
        if ($character->level < $occupation->min_level ||
            ($occupation->max_level !== null && $character->level > $occupation->max_level)) {
            throw new GameActionException('You are not the right level for this work.');
        }

        // Re-fetch fresh, then read, so the reported energy_spent reflects
        // the character's current row (not a stale in-memory value).
        $character->refresh();
        $energyBefore = (int) $character->energy;

        $rate = (int) $occupation->gold_per_energy;

        // Atomic conditional UPDATE + affected-count guard (laravel 12.x/queries):
        // a single UPDATE statement, so the `gold` RHS is evaluated against the
        // pre-update `energy` value for both MySQL and sqlite in one shot — no
        // read-then-write race, no LEAST() (sqlite tests lack it), no
        // transaction needed (single statement is already atomic).
        $affected = Character::whereKey($character->id)
            ->where('energy', '>', 0)
            ->update([
                'gold' => DB::raw('gold + (energy * '.$rate.')'),
                'energy' => 0,
            ]);

        if ($affected === 0) {
            throw new GameActionException('You have no energy to work.');
        }

        $character->refresh();

        return [
            'energy_spent' => $energyBefore,
            'gold_earned' => $energyBefore * $rate,
            'character' => $character,
        ];
    }
}
