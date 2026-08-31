<?php

use App\Livewire\FactionSelect;
use App\Models\Character;
use App\Models\User;
use Livewire\Livewire;

test('a user without a character lands on the faction page instead of home', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/home')->assertRedirect(route('faction.select'));
});

test('every guarded game page bounces a character-less user to the faction page', function (string $path) {
    $user = User::factory()->create();

    $this->actingAs($user)->get($path)->assertRedirect(route('faction.select'));
})->with(['/home', '/work', '/train', '/market', '/battle', '/hospital']);

test('the faction page shows both factions and commits to neither', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(FactionSelect::class)
        ->assertSee('Faction 1')
        ->assertSee('Faction 2')
        ->call('previewFaction', 'faction_1')
        ->assertSet('showPreview', true)
        ->call('previewFaction', 'faction_2')  // freely switched, still nothing written
        ->assertSet('preview', 'faction_2');

    expect($user->fresh()->character)->toBeNull();
});

test('previewing shows that faction description', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(FactionSelect::class)
        ->call('previewFaction', 'faction_1')
        ->assertSee('answers steel with steel')
        ->assertDontSee('answers steel with cunning');
});

test('the headcount is never shown on the faction page', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(FactionSelect::class)
        ->call('previewFaction', 'faction_1')
        ->assertDontSee('Soldiers under this banner');
});

test('confirming an unknown faction is rejected', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(FactionSelect::class)
        ->set('preview', 'faction_9')
        ->call('confirm')
        ->assertStatus(422);

    expect($user->fresh()->character)->toBeNull();
});

test('confirming with nothing previewed is rejected', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(FactionSelect::class)
        ->call('confirm')
        ->assertStatus(422);

    expect($user->fresh()->character)->toBeNull();
});

test('a character that already exists is sent away from the faction page', function () {
    $user = User::factory()->create();
    $user->character()->create(['faction' => 'faction_1']);

    Livewire::actingAs($user)
        ->test(FactionSelect::class)
        ->assertRedirect(route('home'));
});

test('a character forged between mount and confirm keeps its own faction', function () {
    $user = User::factory()->create();

    $page = Livewire::actingAs($user)
        ->test(FactionSelect::class)
        ->call('previewFaction', 'faction_1');

    // Second tab (or a resubmitted request) got there first.
    $user->character()->create(['faction' => 'faction_2']);

    $page->call('confirm')->assertRedirect(route('home'));

    expect($user->fresh()->character->faction)->toEqual('faction_2');
    expect(Character::where('user_id', $user->id)->count())->toEqual(1);
});

test('a user with a character is left alone by the guard', function () {
    $user = User::factory()->create();
    $user->character()->create(['faction' => 'faction_1']);

    $this->actingAs($user)->get('/home')->assertStatus(200);
});
