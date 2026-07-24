<?php

use App\Livewire\Battle;
use App\Models\Character;
use App\Models\CombatLog;
use App\Models\User;
use Livewire\Livewire;

// Determinism strategy: force stats so the attacker's very first swing is a
// guaranteed one-shot KO (defender agility 0 -> never dodges; strength gap
// swamps the ± level variance) so the outcome is deterministic even though
// the component resolves via the app's real, unseeded secure RNG.
test('attacking a valid opponent resolves the fight, flashes no error, logs the combat, and conserves gold between the two characters', function () {
    $attackerUser = User::factory()->create();
    $attacker = Character::create([
        'user_id' => $attackerUser->id,
        'level' => 1,
        'gold' => 50,
        'health' => 200,
        'max_health' => 200,
        'strength' => 1000,
        'defense' => 5,
        'agility' => 5,
    ]);

    $defenderUser = User::factory()->create();
    $defender = Character::create([
        'user_id' => $defenderUser->id,
        'level' => 1,
        'gold' => 100,
        'health' => 100,
        'max_health' => 100,
        'strength' => 5,
        'defense' => 5,
        'agility' => 0,
    ]);
    $goldBefore = $attacker->gold + $defender->gold;

    $this->actingAs($attackerUser);
    $component = Livewire::test(Battle::class)->call('attack', $defender->id);

    expect(session('error'))->toBeNull();
    expect($component->get('lastFight'))->not->toBeEmpty();
    expect($component->get('lastFight.knockout'))->toBeTrue();
    expect(CombatLog::count())->toBe(1);

    $goldAfter = $attacker->fresh()->gold + $defender->fresh()->gold;
    expect($goldAfter)->toBe($goldBefore);
});

test('GET /battle returns 200 for an authenticated user with a character', function () {
    $user = User::factory()->create();
    $user->character()->create([]);

    $response = $this->actingAs($user)->get('/battle');

    $response->assertStatus(200);
});

test('a hospitalized defender is excluded from the opponents list, but a healthy one is included', function () {
    $user = User::factory()->create();
    Character::create(['user_id' => $user->id]);

    $hospitalized = Character::create([
        'user_id' => User::factory()->create()->id,
        'hospitalized_until' => now()->addMinutes(10),
    ]);
    $healthy = Character::create([
        'user_id' => User::factory()->create()->id,
    ]);

    $this->actingAs($user);
    $component = Livewire::test(Battle::class);
    $opponentIds = $component->get('opponents')->pluck('id')->all();

    expect($opponentIds)->not->toContain($hospitalized->id);
    expect($opponentIds)->toContain($healthy->id);
});

test('the acting character is not included in their own opponents list', function () {
    $user = User::factory()->create();
    $character = Character::create(['user_id' => $user->id]);
    Character::create(['user_id' => User::factory()->create()->id]);

    $this->actingAs($user);
    $component = Livewire::test(Battle::class);
    $opponentIds = $component->get('opponents')->pluck('id')->all();

    expect($opponentIds)->not->toContain($character->id);
});
