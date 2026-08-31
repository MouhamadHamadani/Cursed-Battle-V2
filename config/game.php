<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Faction headcount
    |--------------------------------------------------------------------------
    |
    | Whether Home shows how many characters share the viewer's faction.
    | Undecided by design: an honest count reads thin while the population is
    | small, so it stays a switch rather than a rewrite. False hides the line;
    | nothing else about the faction panel changes.
    |
    */

    'faction_headcount' => env('GAME_FACTION_HEADCOUNT', true),

    /*
    |--------------------------------------------------------------------------
    | Landing stats
    |--------------------------------------------------------------------------
    |
    | Whether the public landing page shows the player / character / battle
    | strip. Same call as the headcount above — the figures are real, but real
    | figures look thin on a young server. False drops the whole section.
    |
    */

    'landing_stats' => env('GAME_LANDING_STATS', true),

];
