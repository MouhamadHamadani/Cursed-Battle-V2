<?php

use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\CombatLog;
use App\Models\Item;
use App\Models\User;
use App\Services\CombatService;
use App\Services\GameActionException;
use Random\Engine\Mt19937;
use Random\Randomizer;

// Determinism strategy throughout this file: inject a seeded Randomizer AND
// force stats so the stat gap swamps the ± level variance and (for the
// loser) agility 0 means it never dodges — outcomes below are deterministic
// for ANY seed.
test('a one-shot knockout wins instantly, hospitalizes the loser, and awards the winner xp', function () {
    $attacker = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'xp' => 0,
        'gold' => 0,
        'health' => 100,
        'strength' => 1000,
        'defense' => 5,
        'agility' => 5,
    ]);
    $defender = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'gold' => 100,
        'health' => 100,
        'strength' => 5,
        'defense' => 5,
        'agility' => 0, // never dodges
    ]);

    $result = (new CombatService(new Randomizer(new Mt19937(12345))))->resolve($attacker, $defender);

    expect($result->knockout)->toBeTrue();
    expect($result->rounds)->toBe(1);
    expect($result->winner->id)->toBe($attacker->id);

    $attacker->refresh();
    $defender->refresh();

    expect($defender->health)->toBe(0);
    expect($attacker->health)->toBe(100); // defender never got a turn
    expect($defender->isHospitalized())->toBeTrue();
    // Raw timestamp diff avoids Carbon's diffInMinutes sign ambiguity when comparing a future time to now().
    $secondsUntilRelease = $defender->hospitalized_until->getTimestamp() - now()->getTimestamp();
    expect($secondsUntilRelease)->toBeGreaterThanOrEqual(29 * 60)
        ->and($secondsUntilRelease)->toBeLessThanOrEqual(30 * 60);
    expect($attacker->xp)->toBe(60); // XP_BASE 50 + loser.level(1) * XP_PER_LEVEL 10, no farm-gap halving
});

test('a defender with high effective defense and agility wins the 10-round tiebreak on remaining hp', function () {
    // Attacker: too weak to hurt the defender (floors to 1 dmg, 75% dodged) but never dodges defender's real hits.
    $attacker = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'health' => 1000,
        'strength' => 1,
        'defense' => 5,
        'agility' => 0, // never dodges defender's hits
    ]);
    // Defender: hits hard and is nearly unhittable, so it only ever loses a few chip points.
    $defender = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'health' => 1000,
        'strength' => 50,
        'defense' => 1000,
        'agility' => 200, // dodge-capped at 75%
    ]);

    $result = (new CombatService(new Randomizer(new Mt19937(12345))))->resolve($attacker, $defender);

    expect($result->knockout)->toBeFalse();
    expect($result->rounds)->toBe(CombatService::MAX_ROUNDS);
    expect($result->winner->id)->toBe($defender->id);

    $attacker->refresh();
    $defender->refresh();

    // Bounded regardless of RNG: defender loses at most 10 (10 rounds * MIN_DAMAGE),
    // attacker always takes every hit (dodge 0%) for ~45 per round.
    expect($defender->health)->toBeGreaterThanOrEqual(990);
    expect($attacker->health)->toBeLessThanOrEqual(560);
    expect($defender->health)->toBeGreaterThan($attacker->health);
});

test('effectiveDodgeChance caps at 75 percent and scales linearly below the cap', function () {
    $service = new CombatService;

    expect($service->effectiveDodgeChance(200))->toBe(75);
    expect($service->effectiveDodgeChance(0))->toBe(0);
    expect($service->effectiveDodgeChance(10))->toBe(5);
});

test('every landed hit deals at least the minimum damage floor even when strength is far below defense', function () {
    $attacker = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'health' => 5000,
        'strength' => 1,
        'defense' => 1000,
        'agility' => 100,
    ]);
    $defender = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'health' => 1000,
        'strength' => 5,
        'defense' => 1000,
        'agility' => 0, // never dodges, so every attacker swing deterministically lands
    ]);

    $result = (new CombatService(new Randomizer(new Mt19937(12345))))->resolve($attacker, $defender);

    $attackerHits = collect($result->events)->where('actor', 'attacker')->values();
    expect($attackerHits)->toHaveCount(CombatService::MAX_ROUNDS);
    foreach ($attackerHits as $hit) {
        expect($hit['dodged'])->toBeFalse();
        expect($hit['damage'])->toBe(CombatService::MIN_DAMAGE);
    }

    $defender->refresh();
    expect($defender->health)->toBe(1000 - CombatService::MAX_ROUNDS * CombatService::MIN_DAMAGE);
});

test('winning a fight steals exactly the gold-steal percentage from the loser', function () {
    $attacker = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'gold' => 0,
        'health' => 100,
        'strength' => 1000,
        'defense' => 5,
        'agility' => 5,
    ]);
    $defender = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'gold' => 100,
        'health' => 100,
        'strength' => 5,
        'defense' => 5,
        'agility' => 0,
    ]);

    (new CombatService(new Randomizer(new Mt19937(12345))))->resolve($attacker, $defender);

    $attacker->refresh();
    $defender->refresh();

    expect($defender->gold)->toBe(90); // 100 - 10% of 100
    expect($attacker->gold)->toBe(10); // 0 + 10
});

test('effectiveStats reflects base stats plus only the equipped items deltas', function () {
    $character = Character::create([
        'user_id' => User::factory()->create()->id,
        'strength' => 5,
        'defense' => 5,
        'agility' => 5,
    ]);
    $equippedItem = Item::create([
        'name' => 'Cursed Blade',
        'type' => 'weapon',
        'strength_delta' => 10,
        'defense_delta' => -2,
        'agility_delta' => 3,
        'min_level' => 1,
        'cost' => 100,
    ]);
    CharacterItem::create([
        'character_id' => $character->id,
        'item_id' => $equippedItem->id,
        'equipped' => true,
    ]);
    $unequippedItem = Item::create([
        'name' => 'Spare Dagger',
        'type' => 'weapon',
        'strength_delta' => 999,
        'min_level' => 1,
        'cost' => 10,
    ]);
    CharacterItem::create([
        'character_id' => $character->id,
        'item_id' => $unequippedItem->id,
        'equipped' => false,
    ]);

    $stats = (new CombatService)->effectiveStats($character);

    expect($stats)->toBe([
        'strength' => 15,
        'defense' => 3,
        'agility' => 8,
    ]);
});

test('attacking yourself is rejected and nothing is persisted', function () {
    $character = Character::create([
        'user_id' => User::factory()->create()->id,
        'health' => 100,
        'gold' => 100,
    ]);

    expect(fn () => (new CombatService(new Randomizer(new Mt19937(12345))))->resolve($character, $character))
        ->toThrow(GameActionException::class, 'You cannot attack yourself.');

    expect(CombatLog::count())->toBe(0);
    $character->refresh();
    expect($character->health)->toBe(100);
    expect($character->gold)->toBe(100);
});

test('an attacker who is hospitalized cannot attack and nothing is persisted', function () {
    $attacker = Character::create([
        'user_id' => User::factory()->create()->id,
        'health' => 100,
        'gold' => 100,
        'hospitalized_until' => now()->addMinutes(10),
    ]);
    $defender = Character::create([
        'user_id' => User::factory()->create()->id,
        'health' => 100,
        'gold' => 100,
    ]);

    expect(fn () => (new CombatService(new Randomizer(new Mt19937(12345))))->resolve($attacker, $defender))
        ->toThrow(GameActionException::class, 'You are hospitalized and cannot fight.');

    expect(CombatLog::count())->toBe(0);
    $attacker->refresh();
    $defender->refresh();
    expect($attacker->health)->toBe(100);
    expect($defender->health)->toBe(100);
    expect($attacker->gold)->toBe(100);
    expect($defender->gold)->toBe(100);
});

test('a hospitalized defender cannot be attacked and nothing is persisted', function () {
    $attacker = Character::create([
        'user_id' => User::factory()->create()->id,
        'health' => 100,
        'gold' => 100,
    ]);
    $defender = Character::create([
        'user_id' => User::factory()->create()->id,
        'health' => 100,
        'gold' => 100,
        'hospitalized_until' => now()->addMinutes(10),
    ]);

    expect(fn () => (new CombatService(new Randomizer(new Mt19937(12345))))->resolve($attacker, $defender))
        ->toThrow(GameActionException::class, 'That target is hospitalized and cannot be attacked.');

    expect(CombatLog::count())->toBe(0);
    $attacker->refresh();
    $defender->refresh();
    expect($attacker->health)->toBe(100);
    expect($defender->health)->toBe(100);
    expect($attacker->gold)->toBe(100);
    expect($defender->gold)->toBe(100);
});

test('an attacker with zero health cannot fight and nothing is persisted', function () {
    $attacker = Character::create([
        'user_id' => User::factory()->create()->id,
        'health' => 0,
        'gold' => 100,
    ]);
    $defender = Character::create([
        'user_id' => User::factory()->create()->id,
        'health' => 100,
        'gold' => 100,
    ]);

    expect(fn () => (new CombatService(new Randomizer(new Mt19937(12345))))->resolve($attacker, $defender))
        ->toThrow(GameActionException::class, 'You have no health to fight with.');

    expect(CombatLog::count())->toBe(0);
    $attacker->refresh();
    $defender->refresh();
    expect($attacker->health)->toBe(0);
    expect($defender->health)->toBe(100);
    expect($attacker->gold)->toBe(100);
    expect($defender->gold)->toBe(100);
});

test('two identically seeded Randomizers produce identical getInt sequences', function () {
    $a = new Randomizer(new Mt19937(999));
    $b = new Randomizer(new Mt19937(999));

    $sequenceA = [];
    $sequenceB = [];
    for ($i = 0; $i < 20; $i++) {
        $sequenceA[] = $a->getInt(1, 100);
        $sequenceB[] = $b->getInt(1, 100);
    }

    expect($sequenceA)->toBe($sequenceB);
});

test('resolving a fight writes exactly one combat log with the winner, events, and gold/xp deltas', function () {
    $attacker = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'gold' => 0,
        'xp' => 0,
        'health' => 100,
        'strength' => 1000,
        'defense' => 5,
        'agility' => 5,
    ]);
    $defender = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'gold' => 100,
        'health' => 100,
        'strength' => 5,
        'defense' => 5,
        'agility' => 0,
    ]);

    (new CombatService(new Randomizer(new Mt19937(12345))))->resolve($attacker, $defender);

    expect(CombatLog::count())->toBe(1);

    $log = CombatLog::sole();
    expect($log->attacker_id)->toBe($attacker->id);
    expect($log->defender_id)->toBe($defender->id);
    expect($log->winner_id)->toBe($attacker->id);
    expect($log->events)->not->toBeEmpty();
    expect($log->gold_change)->toBe(10); // attacker won: +10% of loser's gold
    expect($log->xp_change)->toBe(60); // attacker's xp gain, from the attacker's perspective
});
