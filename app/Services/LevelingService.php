<?php

namespace App\Services;

use App\Models\Character;

class LevelingService
{
    /** XP needed to advance from level L = L * XP_PER_LEVEL_STEP. */
    public const XP_PER_LEVEL_STEP = 100;

    /** Permanent max_health/max_energy gained per level. */
    public const HP_PER_LEVEL = 10;

    public const EN_PER_LEVEL = 1;

    /**
     * XP required to advance out of $level. `characters.xp` is progress
     * toward the NEXT level (resets on level-up), not a lifetime total.
     */
    public function threshold(int $level): int
    {
        return $level * self::XP_PER_LEVEL_STEP;
    }

    /**
     * The single XP seam — both CombatService (Phase 6) and WorkService's
     * trickle (Phase 8) call this. Bounded loop: xp strictly decreases each
     * iteration (never a while(true)), so it always terminates. On any
     * level-up, heal/refill to the new max as a reward (ADR-001 §Leveling).
     *
     * @return array{leveled_up: bool, levels_gained: int}
     */
    public function awardXp(Character $character, int $xp): array
    {
        $character->xp += $xp;
        $gained = 0;

        while ($character->xp >= $this->threshold($character->level)) {
            $character->xp -= $this->threshold($character->level);
            $character->level++;
            $character->max_health += self::HP_PER_LEVEL;
            $character->max_energy += self::EN_PER_LEVEL;
            $gained++;
        }

        if ($gained > 0) {
            $character->health = $character->max_health;
            $character->energy = $character->max_energy;
        }

        $character->save();

        return ['leveled_up' => $gained > 0, 'levels_gained' => $gained];
    }
}
