<?php

namespace App\Livewire;

use App\Services\ActivityService;
use App\Services\GameActionException;
use App\Services\TrainingService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts.app')]
class Train extends Component
{
    /** Lazy resolution point (ADR-002 §2): a finished drill lands on page load. */
    public function mount(): void
    {
        app(ActivityService::class)->resolvePending($this->character);
        unset($this->character);
    }

    #[On('character-updated')]
    public function refreshCharacter(): void
    {
        unset($this->character);
    }

    #[Computed]
    public function character()
    {
        return auth()->user()->character;
    }

    /**
     * Train the given stat. All game rules (whitelist, energy check, gain)
     * are re-validated server-side inside TrainingService — this action's
     * $stat is untrusted client input.
     */
    public function train(string $stat): void
    {
        try {
            $result = app(TrainingService::class)->start($this->character, $stat);
            unset($this->character);
            $this->dispatch('character-updated');
            session()->flash('status', 'You begin drilling '.ucfirst($result->stat).'.');
        } catch (GameActionException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.train');
    }
}
