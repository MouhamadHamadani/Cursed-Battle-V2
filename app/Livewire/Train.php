<?php

namespace App\Livewire;

use App\Services\GameActionException;
use App\Services\TrainingService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Train extends Component
{
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
            $result = app(TrainingService::class)->train($this->character, $stat);
            unset($this->character);
            $this->dispatch('character-updated');
            session()->flash('status', ucfirst($result['stat']).' increased.');
        } catch (GameActionException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.train');
    }
}
