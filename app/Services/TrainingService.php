<?php

namespace App\Services;

use App\Models\Character;
use Illuminate\Support\Facades\DB;

class TrainingService
{
    public const ENERGY_COST = 5; // per session, tunable

    public const STAT_GAIN = 1;

    // whitelist — client stat name validated against this
    private const STATS = ['strength', 'defense', 'agility'];

    /**
     * Spend a fixed energy cost to raise one chosen stat by STAT_GAIN.
     *
     * @return array{stat: string, character: Character}
     */
    public function train(Character $character, string $stat): array
    {
        if (! in_array($stat, self::STATS, true)) {
            throw new GameActionException('Unknown stat.'); // never let a client string reach the SQL column name
        }

        // Atomic conditional UPDATE + affected-count guard (laravel 12.x/queries);
        // $stat is whitelisted above, so it is a safe SQL identifier here. No LEAST, no transaction.
        $affected = Character::whereKey($character->id)
            ->where('energy', '>=', self::ENERGY_COST)
            ->update([
                $stat => DB::raw($stat.' + '.self::STAT_GAIN),
                'energy' => DB::raw('energy - '.self::ENERGY_COST),
            ]);

        if ($affected === 0) {
            throw new GameActionException('Not enough energy to train (need '.self::ENERGY_COST.').');
        }

        $character->refresh();

        return ['stat' => $stat, 'character' => $character];
    }
}
