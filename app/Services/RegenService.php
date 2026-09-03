<?php

namespace App\Services;

use App\Models\Character;
use Carbon\CarbonImmutable;
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
     * Minutes between ticks. Must stay in step with the schedule in
     * routes/console.php — RegenServiceTest asserts the two agree, because
     * drift here silently makes every countdown in the UI lie.
     */
    public const TICK_MINUTES = 5;

    /**
     * Slack added to a tick's predicted arrival.
     *
     * The scheduler cron has minute granularity: the tick fires on the first
     * `schedule:run` at or after the boundary, so it can land up to a minute
     * late (in practice it does — the registered task runs at ~:49 past the
     * minute). Predicting the bare boundary would have the UI refresh before
     * the tick has committed, show unchanged figures, and then wait a whole
     * cycle to correct itself.
     *
     * ponytail: a fixed 60s allowance, not a read of when the tick actually
     * lands. Good enough while the trigger is a per-minute cron; if regen ever
     * moves to a sub-minute or event-driven trigger, drop this rather than
     * tuning it.
     */
    public const CRON_GRACE_SECONDS = 60;

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

    /**
     * When the next tick is expected to have landed.
     *
     * Regen is a global cron tick, not a per-character timer, so this is the
     * same instant for every player — it deliberately takes no Character.
     *
     * Always strictly in the future: a countdown rendered against a target of
     * "now" would fire its completion event on its first frame, refresh, and
     * fire again.
     */
    public function nextTickAt(?CarbonImmutable $now = null): CarbonImmutable
    {
        $now = $now ?? CarbonImmutable::now();

        // Rewound by the grace so that a tick already due but not yet
        // committed still counts as the next one. Without the rewind, the
        // whole grace window reports the tick *after* the pending one and the
        // countdown jumps a full cycle for a minute out of every five.
        $base = $now->subSeconds(self::CRON_GRACE_SECONDS);

        return $base->startOfMinute()
            ->addMinutes(self::TICK_MINUTES - ($base->minute % self::TICK_MINUTES))
            ->addSeconds(self::CRON_GRACE_SECONDS);
    }

    /** When energy reaches max_energy, or null if it is already full. */
    public function energyFullAt(Character $character, ?CarbonImmutable $now = null): ?CarbonImmutable
    {
        return $this->fullAt($character->energy, $character->max_energy, self::ENERGY_PER_TICK, $now);
    }

    /** When health reaches max_health, or null if it is already full. */
    public function healthFullAt(Character $character, ?CarbonImmutable $now = null): ?CarbonImmutable
    {
        return $this->fullAt($character->health, $character->max_health, self::HEALTH_PER_TICK, $now);
    }

    /**
     * The tick that closes the gap. One tick away is the next tick itself,
     * hence the -1: n ticks land at nextTickAt + (n-1) intervals.
     *
     * A value already at or above its max returns null — "full" is the absence
     * of a countdown, so callers render nothing rather than a zeroed clock.
     */
    private function fullAt(int $current, int $max, int $perTick, ?CarbonImmutable $now): ?CarbonImmutable
    {
        if ($current >= $max) {
            return null;
        }

        $ticksNeeded = (int) ceil(($max - $current) / $perTick);

        return $this->nextTickAt($now)->addMinutes(($ticksNeeded - 1) * self::TICK_MINUTES);
    }
}
