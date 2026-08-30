<?php

namespace App\Livewire;

use App\Models\Character;
use App\Services\ActivityService;
use App\Services\LevelingService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The global character strip, rendered once by layouts.app and persisted
 * across wire:navigate transitions (@persist('status-bar')).
 *
 * Display only: it reads the character row as it currently stands and asks
 * LevelingService for the XP threshold. It computes nothing itself — V1's
 * Navigation.php duplicated the regen countdown arithmetic outside the
 * services and that is deliberately not carried over.
 */
class StatusBar extends Component
{
    /**
     * Queried directly rather than through auth()->user()->character: that
     * relation is cached on the User instance, so a service mutating the row
     * elsewhere would leave this component re-rendering stale figures when
     * the computed cache is dropped.
     */
    #[Computed]
    public function character(): ?Character
    {
        return Character::firstWhere('user_id', auth()->id());
    }

    /**
     * XP needed to clear the current level. Delegated to the one leveling
     * seam (ADR-001) rather than recomputed here.
     */
    #[Computed]
    public function xpThreshold(): int
    {
        return app(LevelingService::class)->threshold($this->character->level);
    }

    /**
     * Re-read after any action that mutates the character. The component
     * holds no state of its own, so dropping the computed caches is the
     * whole refresh — Work, Train, Battle and Market dispatch this.
     */
    #[On('character-updated')]
    public function onCharacterUpdated(): void
    {
        unset($this->character, $this->xpThreshold);
    }

    /**
     * The countdown badge reached zero. Ask the server to settle the session
     * (idempotent — several listeners may fire at once), then tell every
     * other component to re-read so the badges clear everywhere at once.
     */
    #[On('activity-completed')]
    public function onActivityCompleted(): void
    {
        if ($this->character) {
            app(ActivityService::class)->resolvePending($this->character);
        }

        unset($this->character, $this->xpThreshold);
        $this->dispatch('character-updated');
    }

    public function render()
    {
        return view('livewire.status-bar');
    }
}
