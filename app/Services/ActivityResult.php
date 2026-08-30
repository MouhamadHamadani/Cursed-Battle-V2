<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * Immutable outcome of a timed session (ADR-002 §5). One VO covers both
 * halves of the split: `start()` returns a pending result carrying
 * completesAt, `resolvePending()` returns a completed one carrying the
 * payout. Both services have already persisted everything — this exists
 * purely to hand the result back for rendering, like CombatResult.
 */
final readonly class ActivityResult
{
    public function __construct(
        /** 'train' | 'work' */
        public string $type,
        /** false = just started, true = the session's payout has landed */
        public bool $completed,
        /** When the session ends. Null once completed. */
        public ?Carbon $completesAt = null,
        // train
        public ?string $stat = null,
        public int $statGain = 0,
        // work
        public ?string $occupationName = null,
        public int $energySpent = 0,
        public int $goldEarned = 0,
        public int $xpEarned = 0,
        public bool $leveledUp = false,
    ) {}
}
