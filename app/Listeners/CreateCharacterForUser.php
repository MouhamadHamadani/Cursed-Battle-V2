<?php

namespace App\Listeners;

use App\Models\Character;
use Illuminate\Auth\Events\Registered;

class CreateCharacterForUser
{
    /**
     * Handle the event.
     */
    public function handle(Registered $event): void
    {
        Character::firstOrCreate(['user_id' => $event->user->id]);
    }
}
