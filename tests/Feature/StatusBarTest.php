<?php

use App\Livewire\StatusBar;
use App\Models\Character;
use App\Models\User;
use App\Services\LevelingService;
use Livewire\Livewire;

test('the status bar shows the authenticated character level, xp, gold, health and energy', function () {
    $user = User::factory()->create();
    Character::create([
        'user_id' => $user->id,
        'level' => 3,
        'xp' => 42,
        'gold' => 777,
        'health' => 55,
        'max_health' => 120,
        'energy' => 7,
        'max_energy' => 12,
    ]);

    $this->actingAs($user);

    Livewire::test(StatusBar::class)
        ->assertSee('3')
        ->assertSee('42 / '.app(LevelingService::class)->threshold(3))
        ->assertSee('777')
        ->assertSee('55 / 120')
        ->assertSee('7 / 12');
});

// The bar is wrapped in @persist, so wire:navigate never remounts it — the
// dispatched event is the only thing that refreshes it.
test('the status bar re-reads the character when character-updated is dispatched', function () {
    $user = User::factory()->create();
    // Gold values chosen not to collide with the health/energy readouts,
    // so assertDontSee below actually proves the old value is gone.
    $character = Character::create(['user_id' => $user->id, 'gold' => 4242]);

    $this->actingAs($user);
    $component = Livewire::test(StatusBar::class)->assertSee('4242');

    // Mutate behind the component's back, the way a service call would.
    $character->update(['gold' => 9191]);

    $component->dispatch('character-updated')
        ->assertSee('9191')
        ->assertDontSee('4242');
});

test('the status bar renders nothing for a user with no character', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(StatusBar::class)->assertOk();
});
