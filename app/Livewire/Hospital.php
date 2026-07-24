<?php

namespace App\Livewire;

use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Hospital extends Component
{
    #[Computed]
    public function character()
    {
        return auth()->user()->character;
    }

    public function render()
    {
        return view('livewire.hospital');
    }
}
