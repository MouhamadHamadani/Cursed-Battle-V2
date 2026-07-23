<?php

namespace App\Services;

use App\Models\Character;
use Illuminate\Support\Facades\DB;

class RegenService
{
    /**
     * Energy restored per tick.
     *
     * Initial balance: empty (0) to full (10) in ~50 minutes at 5-minute
     * ticks. Tunable.
     */
    public const ENERGY_PER_TICK = 1;

    /**
     * Health restored per tick.
     *
     * Initial balance: empty (0) to full (100) in ~50 minutes at 5-minute
     * ticks. Tunable.
     */
    public const HEALTH_PER_TICK = 10;

    /**
     * Apply one regen tick to every character, capped at each character's max.
     *
     * Hospitalized characters DO regen — they just can't act (enforced by
     * CombatService in Phase 6/7). MVP simplification.
     */
    public function tick(): void
    {
        Character::whereColumn('energy', '<', 'max_energy')->update([
            'energy' => DB::raw(sprintf(
                'CASE WHEN energy + %1$d >= max_energy THEN max_energy ELSE energy + %1$d END',
                self::ENERGY_PER_TICK
            )),
        ]);

        Character::whereColumn('health', '<', 'max_health')->update([
            'health' => DB::raw(sprintf(
                'CASE WHEN health + %1$d >= max_health THEN max_health ELSE health + %1$d END',
                self::HEALTH_PER_TICK
            )),
        ]);
    }
}
