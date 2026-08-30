<?php

use App\Models\Character;
use App\Models\Occupation;
use App\Models\User;
use App\Services\CombatService;
use App\Services\GameActionException;
use App\Services\TrainingService;
use App\Services\WorkService;

test('isHospitalized returns true while hospitalized_until is in the future', function () {
    $character = Character::create([
        'user_id' => User::factory()->create()->id,
        'hospitalized_until' => now()->addMinutes(30),
    ]);

    expect($character->isHospitalized())->toBeTrue();
});

test('isHospitalized returns false once hospitalized_until has passed', function () {
    $character = Character::create([
        'user_id' => User::factory()->create()->id,
        'hospitalized_until' => now()->addMinutes(30),
    ]);

    $this->travel(31)->minutes();
    expect($character->isHospitalized())->toBeFalse();
    $this->travelBack();
});

test('isHospitalized returns false when hospitalized_until is null', function () {
    $character = Character::create([
        'user_id' => User::factory()->create()->id,
    ]);

    expect($character->isHospitalized())->toBeFalse();
});

// Stats are set so only the hospital pre-check (not a stats-based outcome)
// could plausibly gate the fight: attacker strength 1000, defender agility 0.
test('a hospitalized attacker cannot attack a healthy defender', function () {
    $attacker = Character::create([
        'user_id' => User::factory()->create()->id,
        'health' => 100,
        'strength' => 1000,
        'hospitalized_until' => now()->addMinutes(10),
    ]);
    $defender = Character::create([
        'user_id' => User::factory()->create()->id,
        'health' => 100,
        'agility' => 0,
    ]);

    expect(fn () => app(CombatService::class)->resolve($attacker, $defender))
        ->toThrow(GameActionException::class, 'You are hospitalized and cannot fight.');
});

test('a healthy attacker cannot attack a hospitalized defender', function () {
    $attacker = Character::create([
        'user_id' => User::factory()->create()->id,
        'health' => 100,
        'strength' => 1000,
    ]);
    $defender = Character::create([
        'user_id' => User::factory()->create()->id,
        'health' => 100,
        'agility' => 0,
        'hospitalized_until' => now()->addMinutes(10),
    ]);

    expect(fn () => app(CombatService::class)->resolve($attacker, $defender))
        ->toThrow(GameActionException::class, 'That target is hospitalized and cannot be attacked.');
});

test('a hospitalized character with energy can still work a shift and earn gold', function () {
    $character = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'energy' => 8,
        'gold' => 100,
        'hospitalized_until' => now()->addMinutes(10),
    ]);
    $occupation = Occupation::create([
        'name' => 'Grave Digger',
        'description' => 'Dig graves.',
        'min_level' => 1,
        'max_level' => 5,
        'gold_per_energy' => 2,
    ]);

    expect($character->isHospitalized())->toBeTrue();

    // ADR-002 fork 2: hospital still gates combat only, so a shift starts.
    $result = app(WorkService::class)->start($character, $occupation);

    expect($result->energySpent)->toBe(8);
    expect($character->refresh()->gold)->toBe(100); // not paid yet

    // ADR-002 fork 3: still hospitalized when it lands, and that changes nothing.
    $this->travel(6)->minutes();
    app(WorkService::class)->resolvePending($character);

    $character->refresh();
    expect($character->isHospitalized())->toBeTrue();
    expect($character->gold)->toBe(116); // 100 + 8 x 2

    $this->travelBack();
});

test('a hospitalized character with energy can still train a stat', function () {
    $character = Character::create([
        'user_id' => User::factory()->create()->id,
        'energy' => 10,
        'strength' => 5,
        'hospitalized_until' => now()->addMinutes(10),
    ]);

    expect($character->isHospitalized())->toBeTrue();

    // ADR-002 fork 2: hospital still gates combat only, so a session starts.
    app(TrainingService::class)->start($character, 'strength');

    $character->refresh();
    expect($character->activity_type)->toBe('train');

    // ADR-002 fork 3: still hospitalized when it lands, and that changes nothing.
    $this->travel(6)->minutes();
    app(TrainingService::class)->resolvePending($character);

    $character->refresh();
    expect($character->isHospitalized())->toBeTrue();
    expect($character->strength)->toBe(6);

    $this->travelBack();
});

test('GET /hospital returns 200 for an authenticated user with a character', function () {
    $user = User::factory()->create();
    $user->character()->create([]);

    $response = $this->actingAs($user)->get('/hospital');

    $response->assertStatus(200);
});

test('the dashboard shows the hospital banner when the character is hospitalized', function () {
    $user = User::factory()->create();
    $user->character()->create(['hospitalized_until' => now()->addMinutes(30)]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertSee('In hospital');
});

test('the dashboard does not show the hospital banner when the character is healthy', function () {
    $user = User::factory()->create();
    $user->character()->create([]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertDontSee('In hospital');
});
