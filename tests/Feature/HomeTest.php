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

/*
 * The quick-link cards mirror the two lockouts the services already enforce:
 * busy closes all four (ADR-002 §4), hospitalization closes combat and nothing
 * else (ADR-001 §Hospital). The cards stay links either way — they are marked,
 * not removed — so these assert on the state copy, not on the href.
 */

test('a busy character sees all four quick links marked busy', function () {
    $user = User::factory()->create();
    $user->character()->create([
        'activity_type' => 'work',
        'activity_completes_at' => now()->addMinutes(9),
        'activity_energy_spent' => 5,
        'activity_gold_rate' => 3,
    ]);

    $response = $this->actingAs($user)->get('/home');

    // The card badge, not the status bar's title="Busy" — one per card, plus
    // the banner naming the session above them.
    expect(substr_count($response->getContent(), 'fa-hourglass-half me-1"></i>Busy'))->toBe(4);
    $response->assertSee('At thy labours');
});

test('a hospitalized character sees only the battle link marked', function () {
    $user = User::factory()->create();
    $user->character()->create(['hospitalized_until' => now()->addMinutes(37)]);

    $response = $this->actingAs($user)->get('/home');

    $response->assertSee('In hospital');
    $response->assertSee('Trade energy for gold.');
    $response->assertSee('Sharpen thy stats.');
    $response->assertSee('Arm and armour thyself.');
    // Battle's blurb is the one replaced by the blocked reason.
    $response->assertDontSee('Test thy steel on another.');
});

test('a healthy idle character sees no quick link marked', function () {
    $user = User::factory()->create();
    $user->character()->create([]);

    $response = $this->actingAs($user)->get('/home');

    $response->assertDontSee('fa-hourglass-half me-1"></i>Busy', false);
    $response->assertDontSee('In hospital');
    $response->assertSee('Test thy steel on another.');
});
