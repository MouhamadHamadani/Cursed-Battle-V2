<?php

namespace App\Livewire;

use App\Models\Occupation;
use App\Services\GameActionException;
use App\Services\WorkService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Work extends Component
{
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
            $result = app(WorkService::class)->work($this->character, $occupation);
            unset($this->character);
            session()->flash('status', "You worked and earned {$result['gold_earned']} gold.");
        } catch (GameActionException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.work');
    }
}
