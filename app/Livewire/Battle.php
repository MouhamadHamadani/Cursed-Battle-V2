<?php

namespace App\Livewire;

use App\Models\Character;
use App\Services\CombatService;
use App\Services\GameActionException;
use App\Services\OpponentService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
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

    /**
     * Presentational only: whether the result modal is open. No game rule
     * reads it — it exists because <x-dark-modal> entangles its open state
     * to a Livewire property (the V1 pattern).
     */
    public bool $showResult = false;

    /**
     * Queried directly rather than via auth()->user()->character: that
     * relation is cached on the User instance, and both CombatService and
     * OpponentService write through their own freshly-locked models — the
     * cached one would still render pre-fight gold and health.
     */
    #[Computed]
    public function character(): Character
    {
        return Character::firstWhere('user_id', auth()->id());
    }

    /**
     * The one revealed mark, read from the character row — not from component
     * state, so a refresh shows the same face rather than a free new one.
     */
    #[Computed]
    public function opponent(): ?Character
    {
        return app(OpponentService::class)->current($this->character);
    }

    /**
     * Re-read after anything mutates the character elsewhere on the page — the
     * status bar broadcasts this once a countdown settles a session. Without
     * it the "at thy labours" banner and the Busy-locked Seek/Attack buttons
     * survive the session that caused them, until a full page load. Same
     * listener Work, Train and Home carry; refreshState() dispatches it.
     */
    #[On('character-updated')]
    public function refreshCharacter(): void
    {
        unset($this->character, $this->opponent, $this->searchCost);
    }

    /** Gold the next search costs: 0 for the first, escalating per re-roll. */
    #[Computed]
    public function searchCost(): int
    {
        return app(OpponentService::class)->cost($this->character);
    }

    /**
     * Reveal an opponent, or reject the current one for another. The price
     * and every eligibility rule are decided server-side in OpponentService;
     * this action carries no arguments to trust.
     */
    public function search(): void
    {
        try {
            app(OpponentService::class)->find($this->character);
            $this->refreshState();
        } catch (GameActionException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    /**
     * Attack the revealed opponent. The defender is read from the character
     * row rather than passed in, so there is no id to tamper with; every
     * game rule is still re-validated inside CombatService.
     */
    public function attack(): void
    {
        try {
            $opponent = app(OpponentService::class)->current($this->character);

            if ($opponent === null) {
                throw new GameActionException('Seek a mark before thou swingest.');
            }

            $result = app(CombatService::class)->resolve($this->character, $opponent);
            $this->lastFight = $result->toArray();
            $this->showResult = true;
            $this->refreshState();
        } catch (GameActionException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    /** Drop every computed cache and tell the status bar to re-read. */
    private function refreshState(): void
    {
        unset($this->character, $this->opponent, $this->searchCost);
        $this->dispatch('character-updated');
    }

    public function render()
    {
        return view('livewire.battle');
    }
}
