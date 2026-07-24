<?php

namespace App\Livewire;

use App\Models\Character;
use App\Services\CombatService;
use App\Services\GameActionException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Battle extends Component
{
    /**
     * Render-ready result of the last attack (CombatResult::toArray()).
     * Plain array, not the CombatResult VO — Livewire public properties
     * must not hold Eloquent models (livewire 3.x/properties).
     */
    public array $lastFight = [];

    #[Computed]
    public function character()
    {
        return auth()->user()->character;
    }

    /**
     * Attackable characters: everyone except self and anyone currently
     * hospitalized. Eager-load user to avoid N+1 when the view resolves
     * each opponent's display name (characters have no name column).
     */
    #[Computed]
    public function opponents()
    {
        return Character::with('user')
            ->whereKeyNot($this->character->id)
            ->where(function ($query) {
                $query->whereNull('hospitalized_until')
                    ->orWhere('hospitalized_until', '<=', now());
            })
            ->get();
    }

    /**
     * Attack the given defender. All game rules (self-attack, hospitalized,
     * health) are re-validated server-side inside CombatService — this
     * action's $defenderId is untrusted client input; the button list is
     * not the security boundary.
     */
    public function attack(int $defenderId): void
    {
        try {
            $defender = Character::findOrFail($defenderId);
            $result = app(CombatService::class)->resolve($this->character, $defender);
            $this->lastFight = $result->toArray();
            unset($this->character); // refresh computed (health/gold changed)
        } catch (GameActionException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.battle');
    }
}
