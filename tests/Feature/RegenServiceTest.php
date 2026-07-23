<?php

use App\Models\Character;
use App\Models\User;
use App\Services\RegenService;

test('character below max regens energy and health by one tick', function () {
    $character = Character::create([
        'user_id' => User::factory()->create()->id,
        'energy' => 3,
        'max_energy' => 10,
        'health' => 50,
        'max_health' => 100,
    ]);

    (new RegenService)->tick();

    $character->refresh();

    expect($character->energy)->toEqual(4);
    expect($character->health)->toEqual(60);
});

test('character near max is capped exactly at max and never overshoots', function () {
    $character = Character::create([
        'user_id' => User::factory()->create()->id,
        'energy' => 9,
        'max_energy' => 10,
        // 95 + 10 would overshoot to 105; must cap at 100, not 105.
        'health' => 95,
        'max_health' => 100,
    ]);

    (new RegenService)->tick();

    $character->refresh();

    expect($character->energy)->toEqual(10);
    expect($character->health)->toEqual(100);
});

test('character already at max is left unchanged by a tick', function () {
    $character = Character::create([
        'user_id' => User::factory()->create()->id,
        'energy' => 10,
        'max_energy' => 10,
        'health' => 100,
        'max_health' => 100,
    ]);
    $updatedAt = $character->updated_at;

    (new RegenService)->tick();

    $character->refresh();

    expect($character->energy)->toEqual(10);
    expect($character->health)->toEqual(100);
    // whereColumn('energy', '<', 'max_energy') excludes this row entirely,
    // so the update never touches it.
    expect($character->updated_at)->toEqual($updatedAt);
});

test('a tick only updates characters below max, proving bulk update scoping', function () {
    $below = Character::create([
        'user_id' => User::factory()->create()->id,
        'energy' => 3,
        'max_energy' => 10,
        'health' => 50,
        'max_health' => 100,
    ]);
    $atMax = Character::create([
        'user_id' => User::factory()->create()->id,
        'energy' => 10,
        'max_energy' => 10,
        'health' => 100,
        'max_health' => 100,
    ]);

    (new RegenService)->tick();

    $below->refresh();
    $atMax->refresh();

    expect($below->energy)->toEqual(4);
    expect($below->health)->toEqual(60);
    expect($atMax->energy)->toEqual(10);
    expect($atMax->health)->toEqual(100);
});
