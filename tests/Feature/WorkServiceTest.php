<?php

use App\Models\Character;
use App\Models\Occupation;
use App\Models\User;
use App\Services\GameActionException;
use App\Services\WorkService;

test('qualified character works a shift and earns gold for all spent energy', function () {
    $character = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'energy' => 8,
        'gold' => 100,
    ]);
    $occupation = Occupation::create([
        'name' => 'Grave Digger',
        'description' => 'Dig graves.',
        'min_level' => 1,
        'max_level' => 5,
        'gold_per_energy' => 2,
    ]);

    $result = (new WorkService)->work($character, $occupation);

    expect($result['energy_spent'])->toEqual(8);
    expect($result['gold_earned'])->toEqual(16);
    expect($result['character']->energy)->toEqual(0);
    expect($result['character']->gold)->toEqual(116);
    expect($result['character']->xp)->toEqual(8); // 8 energy spent * XP_PER_ENERGY 1

    $character->refresh();
    expect($character->energy)->toEqual(0);
    expect($character->gold)->toEqual(116);
    expect($character->xp)->toEqual(8);
});

test('a character below the minimum level is rejected and nothing changes', function () {
    $character = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'energy' => 8,
        'gold' => 100,
    ]);
    $occupation = Occupation::create([
        'name' => 'Bone Merchant',
        'description' => 'Trade in relics and remains.',
        'min_level' => 8,
        'max_level' => 20,
        'gold_per_energy' => 7,
    ]);

    expect(fn () => (new WorkService)->work($character, $occupation))
        ->toThrow(GameActionException::class, 'You are not the right level for this work.');

    $character->refresh();
    expect($character->gold)->toEqual(100);
    expect($character->energy)->toEqual(8);
});

test('a character above the maximum level is rejected and nothing changes', function () {
    $character = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 25,
        'energy' => 8,
        'gold' => 100,
    ]);
    $occupation = Occupation::create([
        'name' => 'Grave Digger',
        'description' => 'Dig graves.',
        'min_level' => 1,
        'max_level' => 5,
        'gold_per_energy' => 2,
    ]);

    expect(fn () => (new WorkService)->work($character, $occupation))
        ->toThrow(GameActionException::class, 'You are not the right level for this work.');

    $character->refresh();
    expect($character->gold)->toEqual(100);
    expect($character->energy)->toEqual(8);
});

test('a character with zero energy is rejected and earns no free gold', function () {
    $character = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'energy' => 0,
        'gold' => 100,
    ]);
    $occupation = Occupation::create([
        'name' => 'Grave Digger',
        'description' => 'Dig graves.',
        'min_level' => 1,
        'max_level' => 5,
        'gold_per_energy' => 2,
    ]);

    expect(fn () => (new WorkService)->work($character, $occupation))
        ->toThrow(GameActionException::class, 'You have no energy to work.');

    $character->refresh();
    expect($character->gold)->toEqual(100);
    expect($character->energy)->toEqual(0);
    expect($character->xp)->toEqual(0);
});

test('an occupation with a null max level accepts a high level character', function () {
    $character = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 30,
        'energy' => 5,
        'gold' => 100,
    ]);
    $occupation = Occupation::create([
        'name' => 'Soul Broker',
        'description' => 'Broker deals for the desperate and the damned.',
        'min_level' => 15,
        'max_level' => null,
        'gold_per_energy' => 12,
    ]);

    $result = (new WorkService)->work($character, $occupation);

    expect($result['energy_spent'])->toEqual(5);
    expect($result['gold_earned'])->toEqual(60);

    $character->refresh();
    expect($character->energy)->toEqual(0);
    expect($character->gold)->toEqual(160);
});

// --- Gap-fill: exact level-boundary edges (existing tests above use levels far from the gate, not adjacent to it) ---

test('a character exactly at the occupation minimum level can work', function () {
    $character = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 3,
        'energy' => 5,
        'gold' => 100,
    ]);
    $occupation = Occupation::create([
        'name' => 'Crypt Cleaner',
        'description' => 'Sweep the crypts.',
        'min_level' => 3,
        'max_level' => 5,
        'gold_per_energy' => 2,
    ]);

    $result = (new WorkService)->work($character, $occupation);

    expect($result['gold_earned'])->toEqual(10);
    $character->refresh();
    expect($character->gold)->toEqual(110);
    expect($character->energy)->toEqual(0);
});

test('a character exactly at the occupation maximum level can work', function () {
    $character = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 5,
        'energy' => 5,
        'gold' => 100,
    ]);
    $occupation = Occupation::create([
        'name' => 'Crypt Cleaner',
        'description' => 'Sweep the crypts.',
        'min_level' => 3,
        'max_level' => 5,
        'gold_per_energy' => 2,
    ]);

    $result = (new WorkService)->work($character, $occupation);

    expect($result['gold_earned'])->toEqual(10);
    $character->refresh();
    expect($character->gold)->toEqual(110);
    expect($character->energy)->toEqual(0);
});

test('a character one level above the occupation maximum is rejected', function () {
    $character = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 6,
        'energy' => 5,
        'gold' => 100,
    ]);
    $occupation = Occupation::create([
        'name' => 'Crypt Cleaner',
        'description' => 'Sweep the crypts.',
        'min_level' => 3,
        'max_level' => 5,
        'gold_per_energy' => 2,
    ]);

    expect(fn () => (new WorkService)->work($character, $occupation))
        ->toThrow(GameActionException::class, 'You are not the right level for this work.');

    $character->refresh();
    expect($character->gold)->toEqual(100);
    expect($character->energy)->toEqual(5);
});

test('a character one level below the occupation minimum is rejected', function () {
    $character = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 2,
        'energy' => 5,
        'gold' => 100,
    ]);
    $occupation = Occupation::create([
        'name' => 'Crypt Cleaner',
        'description' => 'Sweep the crypts.',
        'min_level' => 3,
        'max_level' => 5,
        'gold_per_energy' => 2,
    ]);

    expect(fn () => (new WorkService)->work($character, $occupation))
        ->toThrow(GameActionException::class, 'You are not the right level for this work.');

    $character->refresh();
    expect($character->gold)->toEqual(100);
    expect($character->energy)->toEqual(5);
});
