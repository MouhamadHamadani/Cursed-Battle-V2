<?php

namespace App\Services;

use App\Models\Character;
use App\Models\CombatLog;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Social-proof counts for the public landing page.
 *
 * No game logic — three reads. It lives here rather than in the view because
 * nothing in this app queries from Blade, and because the landing page is the
 * one public, unauthenticated route: three uncached COUNT(*)s per anonymous
 * hit is the wrong default. Five minutes is stale enough to be cheap and
 * fresh enough that a new player sees the number move.
 */
class LandingStats
{
    /** @return array{players: int, characters: int, battles: int} */
    public function counts(): array
    {
        return Cache::remember('landing.stats', now()->addMinutes(5), fn () => [
            'players' => User::count(),
            'characters' => Character::count(),
            'battles' => CombatLog::count(),
        ]);
    }
}
