<?php

use App\Models\Character;
use App\Models\User;
use App\Services\LevelingService;

test('award below threshold accrues xp without leveling up', function () {
    $character = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'xp' => 0,
        'health' => 100,
        'max_health' => 100,
        'energy' => 10,
        'max_energy' => 10,
    ]);

    $result = app(LevelingService::class)->awardXp($character, 50);

    expect($character->xp)->toBe(50);
    expect($character->level)->toBe(1);
    expect($character->health)->toBe(100); // untouched, no heal on non-level-up
    expect($result)->toBe(['leveled_up' => false, 'levels_gained' => 0]);
});

test('award exactly the threshold levels up once, resets xp, and heals/refills to the new max', function () {
    $character = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'xp' => 0,
        'health' => 20,
        'max_health' => 100,
        'energy' => 10,
        'max_energy' => 10,
    ]);

    $result = app(LevelingService::class)->awardXp($character, 100);

    expect($character->level)->toBe(2);
    expect($character->xp)->toBe(0);
    expect($character->max_health)->toBe(110);
    expect($character->max_energy)->toBe(11);
    expect($character->health)->toBe(110); // healed to the new max, not the old one
    expect($character->energy)->toBe(11);
    expect($result)->toBe(['leveled_up' => true, 'levels_gained' => 1]);
});

test('a large single award multi-levels with correct carryover xp', function () {
    $character = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'xp' => 0,
        'health' => 100,
        'max_health' => 100,
        'energy' => 10,
        'max_energy' => 10,
    ]);

    // threshold(1)=100, threshold(2)=200: 350-100=250, 250-200=50, 50 < threshold(3)=300 -> stop.
    $result = app(LevelingService::class)->awardXp($character, 350);

    expect($character->level)->toBe(3);
    expect($character->xp)->toBe(50);
    expect($character->max_health)->toBe(120);
    expect($character->max_energy)->toBe(12);
    expect($result)->toBe(['leveled_up' => true, 'levels_gained' => 2]);
});

test('carryover xp is exact when pre-existing xp plus the award crosses the threshold', function () {
    $character = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'xp' => 30,
        'health' => 100,
        'max_health' => 100,
        'energy' => 10,
        'max_energy' => 10,
    ]);

    // 30 + 90 = 120; 120 - threshold(1)=100 = 20 leftover.
    $result = app(LevelingService::class)->awardXp($character, 90);

    expect($character->level)->toBe(2);
    expect($character->xp)->toBe(20);
    expect($result)->toBe(['leveled_up' => true, 'levels_gained' => 1]);
});

test('a damaged character that levels up is healed to the full new max health and energy', function () {
    $character = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'xp' => 0,
        'health' => 5,
        'max_health' => 100,
        'energy' => 0,
        'max_energy' => 10,
    ]);

    app(LevelingService::class)->awardXp($character, 100);

    expect($character->health)->toBe(110);
    expect($character->energy)->toBe(11);
});

test('a below-threshold award still persists xp to the database', function () {
    $character = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'xp' => 0,
        'health' => 100,
        'max_health' => 100,
        'energy' => 10,
        'max_energy' => 10,
    ]);

    app(LevelingService::class)->awardXp($character, 50);

    $reloaded = Character::find($character->id);
    expect($reloaded->xp)->toBe(50);
    expect($reloaded->level)->toBe(1);
});
