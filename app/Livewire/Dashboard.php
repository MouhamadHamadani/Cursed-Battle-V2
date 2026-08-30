<?php

namespace App\Livewire;

use App\Models\Character;
use App\Services\ActivityService;
use Livewire\Attributes\On;
use Livewire\Component;

class Dashboard extends Component
{
    /** Lazy resolution point (ADR-002 §2): a finished session lands on page load. */
    public function mount(): void
    {
        if ($character = $this->character()) {
            app(ActivityService::class)->resolvePending($character);
        }
    }

    #[On('character-updated')]
    public function refreshCharacter(): void
    {
        // No state to clear — the re-render re-queries.
    }

    /**
     * Queried directly rather than via auth()->user()->character: that
     * relation is cached on the User instance and would render stale figures
     * right after a session resolves in the same request.
     */
    private function character(): ?Character
    {
        return Character::firstWhere('user_id', auth()->id());
    }

    public function render()
    {
        return view('livewire.dashboard', [
            'character' => $this->character(),
        ]);
    }
}
