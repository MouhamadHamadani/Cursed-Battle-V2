<?php

namespace App\Services;

use App\Models\Character;

class LevelingService
{
    /**
     * The single XP seam — both CombatService (Phase 6) and, later,
     * WorkService's trickle (Phase 8) call this. This phase is
     * increment-only: no level-up processing yet. Phase 8 enriches this
     * method with the level-up loop behind this exact signature, so
     * callers never change (ADR-001 §Leveling).
     *
     * @return array{leveled_up: bool, levels_gained: int}
     */
    public function awardXp(Character $character, int $xp): array
    {
        $character->increment('xp', $xp);

        return ['leveled_up' => false, 'levels_gained' => 0];
    }
}
