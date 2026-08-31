<?php

use App\Livewire\Home;
use App\Models\User;
use Livewire\Livewire;

test('home displays the authenticated user character stats', function () {
    $user = User::factory()->create();
    $user->character()->create([]);

    $response = $this->actingAs($user)->get('/home');

    $response->assertStatus(200);
    $response->assertSee(['Level', 'Gold', '100']);
});

test('home shows the character faction', function () {
    $user = User::factory()->create();
    $user->character()->create(['faction' => 'faction_2']);

    $response = $this->actingAs($user)->get('/home');

    $response->assertStatus(200);
    $response->assertSee(['Faction', 'Faction 2']);
    $response->assertSee('answers steel with cunning');
});

test('home counts only characters in the viewer own faction', function () {
    $user = User::factory()->create();
    $user->character()->create(['faction' => 'faction_1']);
    User::factory()->create()->character()->create(['faction' => 'faction_1']);
    User::factory()->create()->character()->create(['faction' => 'faction_2']);

    Livewire::actingAs($user)->test(Home::class)
        ->assertSee('Soldiers under this banner')
        ->assertViewHas('factionCount', 2);
});

test('the headcount is hidden when the config flag is off', function () {
    config(['game.faction_headcount' => false]);

    $user = User::factory()->create();
    $user->character()->create(['faction' => 'faction_1']);

    Livewire::actingAs($user)->test(Home::class)
        ->assertViewHas('factionCount', null)
        ->assertDontSee('Soldiers under this banner')
        // The rest of the faction panel is unaffected by the toggle.
        ->assertSee('answers steel with steel');
});
