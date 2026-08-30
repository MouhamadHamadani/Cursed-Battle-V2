<?php

namespace App\Services;

use App\Models\Character;
use App\Models\Occupation;
use Illuminate\Support\Facades\DB;

class WorkService
{
    /** XP trickle per energy spent on a shift (Phase 8, ADR-001 §Leveling). */
    public const XP_PER_ENERGY = 1;

    /** Session length at level 1, in seconds (ADR-002 §Tunables). */
    public const WORK_BASE_SECONDS = 300;

    /** Added per level above 1 — double Train's slope (ADR-002 §Tunables). */
    public const WORK_SECONDS_PER_LEVEL = 60;

    /**
     * Linear in level: 5m00s at L1, 9m00s at L5, 14m00s at L10, 24m00s at
     * L20, 54m00s at L50 (ADR-002 §1).
     */
    public static function durationFor(int $level): int
    {
        return self::WORK_BASE_SECONDS + self::WORK_SECONDS_PER_LEVEL * ($level - 1);
    }

    /**
     * Begin a shift: the whole energy bar is spent now, gold and the XP
     * trickle land when the shift completes. The payout basis
     * (energy spent × gold rate) is snapshotted onto the character so
     * retuning or deleting the occupation mid-shift cannot change or break
     * a reward already promised (ADR-002 §2).
     */
    public function start(Character $character, Occupation $occupation): ActivityResult
    {
        // Level gate: max_level null = no upper cap.
        if ($character->level < $occupation->min_level ||
            ($occupation->max_level !== null && $character->level > $occupation->max_level)) {
            throw new GameActionException('You are not the right level for this work.');
        }

        // Resolve first, then check busy (ADR-002 §4).
        app(ActivityService::class)->resolvePending($character);

        if ($character->isBusy()) {
            throw new GameActionException('Thou art already '.ActivityService::describe($character->activity_type).'. Finish before taking up another task.');
        }

        $completesAt = now()->addSeconds(self::durationFor((int) $character->level));

        // Single atomic UPDATE: activity_energy_spent is assigned from `energy`
        // BEFORE `energy` is zeroed. MySQL evaluates SET clauses left to right
        // against already-updated values, so the order of these array keys is
        // load-bearing; sqlite uses the pre-update row either way.
        $affected = Character::whereKey($character->id)
            ->where('energy', '>', 0)
            ->whereNull('activity_type')
            ->update([
                'activity_energy_spent' => DB::raw('energy'),
                'activity_type' => 'work',
                'activity_occupation_id' => $occupation->id,
                'activity_gold_rate' => (int) $occupation->gold_per_energy,
                'activity_completes_at' => $completesAt,
                'energy' => 0,
            ]);

        if ($affected === 0) {
            $character->refresh();

            if ($character->activity_type !== null) {
                throw new GameActionException('Thou art already '.ActivityService::describe($character->activity_type).'. Finish before taking up another task.');
            }

            throw new GameActionException('You have no energy to work.');
        }

        $character->refresh();

        return new ActivityResult(
            type: 'work',
            completed: false,
            completesAt: $completesAt,
            occupationName: $occupation->name,
            energySpent: (int) $character->activity_energy_spent,
        );
    }

    /**
     * Pay out a due shift, exactly once.
     *
     * Gold and the XP award are two writes, so the claim and the award run in
     * one transaction (the MarketService pattern) — a crash between them
     * cannot pay gold and lose the XP. A concurrent second call claims
     * nothing and returns null (ADR-002 §2).
     */
    public function resolvePending(Character $character): ?ActivityResult
    {
        $character->refresh();

        if ($character->activity_type !== 'work'
            || $character->activity_completes_at === null
            || $character->activity_completes_at->isFuture()) {
            return null;
        }

        // Snapshotted at start — deliberately NOT re-read from the occupation.
        $energySpent = (int) $character->activity_energy_spent;
        $goldEarned = $energySpent * (int) $character->activity_gold_rate;
        $xpEarned = $energySpent * self::XP_PER_ENERGY;

        // Display only, and nullable: the occupation may have been deleted
        // mid-shift (nullOnDelete). The payout above does not depend on it.
        $occupationName = $character->activity_occupation_id !== null
            ? Occupation::find($character->activity_occupation_id)?->name
            : null;

        return DB::transaction(function () use ($character, $energySpent, $goldEarned, $xpEarned, $occupationName) {
            $affected = Character::whereKey($character->id)
                ->where('activity_type', 'work')
                ->where('activity_completes_at', '<=', now())
                ->update([
                    'gold' => DB::raw('gold + '.$goldEarned),
                    'activity_type' => null,
                    'activity_stat' => null,
                    'activity_occupation_id' => null,
                    'activity_energy_spent' => null,
                    'activity_gold_rate' => null,
                    'activity_completes_at' => null,
                ]);

            if ($affected === 0) {
                return null; // already resolved by a concurrent request
            }

            $character->refresh();

            $levelResult = app(LevelingService::class)->awardXp($character, $xpEarned);
            $character->refresh();

            return new ActivityResult(
                type: 'work',
                completed: true,
                occupationName: $occupationName,
                energySpent: $energySpent,
                goldEarned: $goldEarned,
                xpEarned: $xpEarned,
                leveledUp: $levelResult['leveled_up'],
            );
        });
    }
}
