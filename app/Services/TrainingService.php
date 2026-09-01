<?php

namespace App\Services;

use App\Models\Character;
use Illuminate\Support\Facades\DB;

class TrainingService
{
    public const ENERGY_COST = 5; // per session, tunable

    public const STAT_GAIN = 1;

    /** Session length at level 1, in seconds (ADR-002 §Tunables). */
    public const TRAIN_BASE_SECONDS = 300;

    /** Added per level above 1 (ADR-002 §Tunables). */
    public const TRAIN_SECONDS_PER_LEVEL = 30;

    // whitelist — client stat name validated against this
    private const STATS = ['strength', 'defense', 'speed', 'dexterity'];

    /**
     * Linear in level: 5m00s at L1, 7m00s at L5, 9m30s at L10, 14m30s at
     * L20, 29m30s at L50 (ADR-002 §1).
     */
    public static function durationFor(int $level): int
    {
        return self::TRAIN_BASE_SECONDS + self::TRAIN_SECONDS_PER_LEVEL * ($level - 1);
    }

    /**
     * Begin a training session: spend the energy now, apply the stat when
     * the session completes. Energy is debited up front so an abandoned
     * session is self-punishing rather than free (ADR-002 §Trade-offs).
     */
    public function start(Character $character, string $stat): ActivityResult
    {
        if (! in_array($stat, self::STATS, true)) {
            throw new GameActionException('Unknown stat.'); // never let a client string reach the SQL column name
        }

        // Resolve first, then check busy — a session that has already run its
        // time clears itself here and this start proceeds (ADR-002 §4).
        app(ActivityService::class)->resolvePending($character);

        if ($character->isBusy()) {
            throw new GameActionException('Thou art already '.ActivityService::describe($character->activity_type).'. Finish before taking up another task.');
        }

        $completesAt = now()->addSeconds(self::durationFor((int) $character->level));

        // Atomic conditional UPDATE + affected-count guard (laravel 12.x/queries).
        // whereNull('activity_type') makes two simultaneous starts impossible:
        // only one can claim the row.
        $affected = Character::whereKey($character->id)
            ->where('energy', '>=', self::ENERGY_COST)
            ->whereNull('activity_type')
            ->update([
                'energy' => DB::raw('energy - '.self::ENERGY_COST),
                'activity_type' => 'train',
                'activity_stat' => $stat,
                'activity_energy_spent' => self::ENERGY_COST,
                'activity_completes_at' => $completesAt,
            ]);

        if ($affected === 0) {
            // Distinguish the two reasons rather than blaming energy for a lost race.
            $character->refresh();

            if ($character->activity_type !== null) {
                throw new GameActionException('Thou art already '.ActivityService::describe($character->activity_type).'. Finish before taking up another task.');
            }

            throw new GameActionException('Not enough energy to train (need '.self::ENERGY_COST.').');
        }

        $character->refresh();

        return new ActivityResult(
            type: 'train',
            completed: false,
            completesAt: $completesAt,
            stat: $stat,
        );
    }

    /**
     * Apply a due training session's stat gain, exactly once.
     *
     * Single write, so no transaction is needed — the conditional UPDATE is
     * itself the claim. A concurrent second call affects 0 rows and returns
     * null rather than throwing: "nothing to do" is not an error (ADR-002 §2).
     */
    public function resolvePending(Character $character): ?ActivityResult
    {
        $character->refresh();

        if ($character->activity_type !== 'train'
            || $character->activity_completes_at === null
            || $character->activity_completes_at->isFuture()) {
            return null;
        }

        $stat = $character->activity_stat;

        if (! in_array($stat, self::STATS, true)) {
            return null; // defensive: never interpolate an unexpected column name
        }

        $affected = Character::whereKey($character->id)
            ->where('activity_type', 'train')
            ->where('activity_completes_at', '<=', now())
            ->update([
                $stat => DB::raw($stat.' + '.self::STAT_GAIN),
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

        return new ActivityResult(
            type: 'train',
            completed: true,
            stat: $stat,
            statGain: self::STAT_GAIN,
        );
    }
}
