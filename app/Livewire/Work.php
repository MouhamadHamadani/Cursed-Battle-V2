<?php

namespace App\Livewire;

use App\Models\Occupation;
use App\Services\ActivityService;
use App\Services\GameActionException;
use App\Services\WorkService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts.app')]
class Work extends Component
{
    /** Lazy resolution point (ADR-002 §2): a finished shift lands on page load. */
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

    #[Computed]
    public function occupations()
    {
        return Occupation::orderBy('min_level')->get();
    }

    /**
     * Work a shift at the given occupation. All game rules (level gate,
     * energy check, payout) are re-validated server-side inside
     * WorkService — this action's $occupationId is untrusted client input.
     */
    public function work(int $occupationId): void
    {
        $occupation = Occupation::findOrFail($occupationId);

        try {
            $result = app(WorkService::class)->start($this->character, $occupation);
            unset($this->character);
            $this->dispatch('character-updated');
            session()->flash('status', "You begin a shift as a {$result->occupationName}.");
        } catch (GameActionException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.work');
    }
}
