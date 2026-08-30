<?php

namespace App\Services;

use App\Models\Character;

/**
 * The single seam for timed sessions (ADR-002 §5), the way
 * LevelingService::awardXp() is the single XP seam in ADR-001. Combat,
 * Market and the page components call resolvePending() without needing to
 * know whether a Train or a Work session is outstanding.
 */
final class ActivityService
{
    /**
     * Resolve whatever session is pending, if it is due. Idempotent and safe
     * to call on every request: returns null when there is nothing to do.
     */
    public function resolvePending(Character $character): ?ActivityResult
    {
        // Dispatch on the row as it stands, not on a possibly stale instance
        // the caller has been holding.
        $character->refresh();

        return match ($character->activity_type) {
            'train' => app(TrainingService::class)->resolvePending($character),
            'work' => app(WorkService::class)->resolvePending($character),
            default => null,
        };
    }

    /**
     * In-character description of what a busy character is doing, so each
     * guard can compose its own refusal in V1's voice (ADR-002 §4).
     * Deterministic on purpose — the random flavour lives in the blocked-state
     * panels in the views, where varying copy costs nothing to assert.
     */
    public static function describe(?string $activityType): string
    {
        return $activityType === 'work' ? 'at thy labours' : 'at the training yard';
    }
}
