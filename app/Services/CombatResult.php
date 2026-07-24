<?php

namespace App\Services;

use App\Models\Character;

/**
 * Immutable outcome of CombatService::resolve(). resolve() has already
 * persisted every consequence (health, hospitalization, gold, xp, the
 * combat_logs row) — this VO exists purely to hand the result back to the
 * caller for rendering. Callers needing Eloquent models (e.g. programmatic
 * checks) use the properties directly; toArray() flattens to a render-ready
 * plain array for a Livewire public property (Phase J) — models never get
 * stored as a Livewire property (livewire 3.x/properties).
 */
final readonly class CombatResult
{
    public function __construct(
        public Character $winner,
        public Character $loser,
        public Character $attacker,
        public Character $defender,
        public array $events,
        public int $goldChange,
        public int $xpChange,
        public bool $leveledUp,
        public int $rounds,
        public bool $knockout,
    ) {}

    /**
     * Render-ready plain array. gold_change/xp_change are from the
     * attacker's perspective, matching the combat_logs convention.
     *
     * @return array{winner_name: string, loser_name: string, attacker_name: string, defender_name: string, knockout: bool, rounds: int, gold_change: int, xp_change: int, leveled_up: bool, events: array, attacker_health: int, defender_health: int}
     */
    public function toArray(): array
    {
        return [
            'winner_name' => $this->displayName($this->winner),
            'loser_name' => $this->displayName($this->loser),
            'attacker_name' => $this->displayName($this->attacker),
            'defender_name' => $this->displayName($this->defender),
            'knockout' => $this->knockout,
            'rounds' => $this->rounds,
            'gold_change' => $this->goldChange,
            'xp_change' => $this->xpChange,
            'leveled_up' => $this->leveledUp,
            'events' => $this->events,
            'attacker_health' => $this->attacker->health,
            'defender_health' => $this->defender->health,
        ];
    }

    /**
     * Characters have no name column (CLAUDE.md data model) — the display
     * name comes from the owning User.
     */
    private function displayName(Character $character): string
    {
        return $character->user?->name ?? ('Character #'.$character->id);
    }
}
