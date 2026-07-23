<?php

use App\Models\Character;
use App\Models\User;
use App\Services\GameActionException;
use App\Services\TrainingService;

test('training a stat with enough energy raises the stat and spends energy', function () {
    $character = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'energy' => 10,
        'strength' => 5,
        'defense' => 5,
        'agility' => 5,
    ]);

    $result = (new TrainingService)->train($character, 'strength');

    expect($result['stat'])->toBe('strength');
    expect($result['character']->strength)->toEqual(6);
    expect($result['character']->energy)->toEqual(5);

    $character->refresh();
    expect($character->strength)->toEqual(6);
    expect($character->energy)->toEqual(5);
});

test('training one stat leaves the other stats unchanged', function () {
    $character = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'energy' => 10,
        'strength' => 5,
        'defense' => 5,
        'agility' => 5,
    ]);

    (new TrainingService)->train($character, 'strength');

    $character->refresh();
    expect($character->defense)->toEqual(5);
    expect($character->agility)->toEqual(5);
});

test('training at exactly the energy cost boundary succeeds and leaves zero energy', function () {
    $character = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'energy' => TrainingService::ENERGY_COST,
        'strength' => 5,
        'defense' => 5,
        'agility' => 5,
    ]);

    $result = (new TrainingService)->train($character, 'agility');

    expect($result['character']->agility)->toEqual(6);
    expect($result['character']->energy)->toEqual(0);

    $character->refresh();
    expect($character->agility)->toEqual(6);
    expect($character->energy)->toEqual(0);
});

test('training one energy below the cost is rejected and nothing changes', function () {
    $character = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'energy' => TrainingService::ENERGY_COST - 1,
        'strength' => 5,
        'defense' => 5,
        'agility' => 5,
    ]);

    expect(fn () => (new TrainingService)->train($character, 'defense'))
        ->toThrow(GameActionException::class, 'Not enough energy to train (need '.TrainingService::ENERGY_COST.').');

    $character->refresh();
    expect($character->defense)->toEqual(5);
    expect($character->energy)->toEqual(TrainingService::ENERGY_COST - 1);
});

test('an invalid stat name is rejected and no column is touched, proving the whitelist blocks column injection', function () {
    $character = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'energy' => 10,
        'gold' => 100,
        'strength' => 5,
        'defense' => 5,
        'agility' => 5,
    ]);

    foreach (['level', 'gold', 'max_health'] as $invalidStat) {
        expect(fn () => (new TrainingService)->train($character, $invalidStat))
            ->toThrow(GameActionException::class, 'Unknown stat.');
    }

    $character->refresh();
    expect($character->level)->toEqual(1);
    expect($character->gold)->toEqual(100);
    expect($character->max_health)->toEqual(100);
    expect($character->strength)->toEqual(5);
    expect($character->defense)->toEqual(5);
    expect($character->agility)->toEqual(5);
    expect($character->energy)->toEqual(10);
});
