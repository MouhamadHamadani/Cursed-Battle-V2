<?php

use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\CombatLog;
use App\Models\Item;
use App\Models\Occupation;
use App\Models\User;
use App\Services\CombatService;
use App\Services\GameActionException;
use App\Services\TrainingService;
use App\Services\WorkService;
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

// --- Gap-fill: turn order, in-fight dodge, anti-farm XP, zero-gold steal, non-KO tiebreak win ---

test('the higher-agility defender acts first and can knock out the attacker before it swings', function () {
    $attacker = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'health' => 100,
        'strength' => 5,
        'defense' => 5,
        'agility' => 50,
    ]);
    $defender = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'health' => 100,
        'strength' => 1000,
        'defense' => 5,
        'agility' => 100, // higher than attacker's 50 -> defender acts first
    ]);

    $result = (new CombatService(new Randomizer(new Mt19937(12345))))->resolve($attacker, $defender);

    expect($result->winner->id)->toBe($defender->id);
    expect($result->knockout)->toBeTrue();
    expect($result->rounds)->toBe(1);
    expect($result->events[0]['actor'])->toBe('defender');
    expect($result->events[0]['dodged'])->toBeFalse();
    expect($result->events[0]['target_hp'])->toBe(0);

    $attacker->refresh();
    expect($attacker->health)->toBe(0); // never got a turn
});

test('mirror: the higher-agility attacker acts first and can knock out the defender before it swings', function () {
    $attacker = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'health' => 100,
        'strength' => 1000,
        'defense' => 5,
        'agility' => 100, // higher than defender's 50 -> attacker acts first
    ]);
    $defender = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'health' => 100,
        'strength' => 5,
        'defense' => 5,
        'agility' => 50,
    ]);

    $result = (new CombatService(new Randomizer(new Mt19937(12345))))->resolve($attacker, $defender);

    expect($result->winner->id)->toBe($attacker->id);
    expect($result->knockout)->toBeTrue();
    expect($result->rounds)->toBe(1);
    expect($result->events[0]['actor'])->toBe('attacker');
    expect($result->events[0]['dodged'])->toBeFalse();
    expect($result->events[0]['target_hp'])->toBe(0);

    $defender->refresh();
    expect($defender->health)->toBe(0); // never got a turn
});

test('a dodge actually occurs inside a real fight and deals zero damage', function () {
    // Seed 12345 (this file's standard seed) confirmed, by running the sim
    // standalone rather than assuming: the defender's 75%-capped dodge
    // chance (agility 150) dodges 8 of the attacker's 10 swings. Every other
    // combat test in this file uses agility 0 (never dodges) -- this is the
    // only test that exercises the dodged:true branch inside resolve()
    // itself (effectiveDodgeChance is otherwise only unit-tested in
    // isolation above).
    $attacker = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'health' => 100,
        'strength' => 30,
        'defense' => 5,
        'agility' => 0,
    ]);
    $defender = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'health' => 300,
        'strength' => 5,
        'defense' => 5,
        'agility' => 150, // dodge-capped at 75%
    ]);

    $result = (new CombatService(new Randomizer(new Mt19937(12345))))->resolve($attacker, $defender);

    $attackerSwings = collect($result->events)->where('actor', 'attacker');
    $dodged = $attackerSwings->where('dodged', true);

    // Not vacuous: some swings dodge AND some land, proving both branches ran.
    expect($dodged)->not->toBeEmpty();
    expect($attackerSwings->where('dodged', false))->not->toBeEmpty();
    foreach ($dodged as $event) {
        expect($event['damage'])->toBe(0);
    }
});

test('winner xp is halved by the anti-farm gap when a high-level attacker beats a much lower-level defender', function () {
    $attacker = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 20,
        'xp' => 0,
        'health' => 100,
        'strength' => 1000,
    ]);
    $defender = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'health' => 100,
        'gold' => 100,
        'agility' => 0,
    ]);

    $result = (new CombatService(new Randomizer(new Mt19937(12345))))->resolve($attacker, $defender);

    // xp = XP_BASE 50 + loser.level(1) * XP_PER_LEVEL 10 = 60; winner.level(20) >
    // loser.level(1) + FARM_GAP(5) is true, so it's halved: floor(60 / 2) = 30.
    expect($result->xpChange)->toBe(30);

    $attacker->refresh();
    expect($attacker->xp)->toBe(30); // started at 0; threshold(20)=2000 so no level-up interferes
});

test('gold steal from a loser with zero gold transfers nothing and leaves both balances unchanged', function () {
    $attacker = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'gold' => 100,
        'health' => 100,
        'strength' => 1000,
    ]);
    $defender = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'gold' => 0,
        'health' => 100,
        'agility' => 0,
    ]);

    $result = (new CombatService(new Randomizer(new Mt19937(12345))))->resolve($attacker, $defender);

    expect($result->goldChange)->toBe(0);

    $attacker->refresh();
    $defender->refresh();
    expect($attacker->gold)->toBe(100); // unchanged: floor(0 * 0.10) = 0
    expect($defender->gold)->toBe(0);
});

test('an attacker who wins the 10-round tiebreak on remaining hp still hospitalizes the loser and moves gold', function () {
    // Deterministic for ANY seed, not just the one below: the defender's
    // damage is strength(5) - defense(5) = 0, plus at most +-level(1)
    // variance, which always floors to MIN_DAMAGE 1 -- so the attacker's
    // health after 10 rounds is always exactly 100 - 10 = 90. The attacker's
    // damage floor (10 - 5 - 1 = 4/round minimum) caps the defender's worst
    // case at 120 - 40 = 80, which is always < 90 -- so the attacker always
    // wins the tiebreak and neither side is ever knocked out.
    $attacker = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'gold' => 0,
        'health' => 100,
        'strength' => 10,
        'defense' => 5,
        'agility' => 0,
    ]);
    $defender = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'gold' => 100,
        'health' => 120,
        'strength' => 5,
        'defense' => 5,
        'agility' => 0, // tie -> attacker acts first
    ]);

    $result = (new CombatService(new Randomizer(new Mt19937(12345))))->resolve($attacker, $defender);

    expect($result->knockout)->toBeFalse();
    expect($result->rounds)->toBe(CombatService::MAX_ROUNDS);
    expect($result->winner->id)->toBe($attacker->id);

    $attacker->refresh();
    $defender->refresh();

    expect($attacker->health)->toBe(90);
    expect($defender->health)->toBeGreaterThanOrEqual(60)->and($defender->health)->toBeLessThanOrEqual(80);
    expect($attacker->health)->toBeGreaterThan($defender->health);

    expect($defender->isHospitalized())->toBeTrue();
    expect($defender->gold)->toBe(90); // 100 - 10% steal
    expect($attacker->gold)->toBe(10);
});

test('an exact remaining-hp tie after the round cap is resolved in the defenders favour', function () {
    // Both fighters are identical with strength == defense (base damage 0), so
    // every hit is max(MIN_DAMAGE 1, 0 +- variance) == exactly 1 for ANY seed.
    // After MAX_ROUNDS with no KO both sit at exactly 40 HP -- a true tie, which
    // ADR-001 resolves in the defender's favour (no draw in MVP). This is the
    // only test that exercises that exact-equality tiebreak branch.
    $attacker = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'gold' => 100,
        'health' => 50,
        'strength' => 5,
        'defense' => 5,
        'agility' => 0,
    ]);
    $defender = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'gold' => 0,
        'health' => 50,
        'strength' => 5,
        'defense' => 5,
        'agility' => 0,
    ]);

    $result = (new CombatService(new Randomizer(new Mt19937(777))))->resolve($attacker, $defender);

    expect($result->knockout)->toBeFalse();
    expect($result->rounds)->toBe(CombatService::MAX_ROUNDS);
    expect($result->winner->id)->toBe($defender->id); // exact HP tie -> defender wins

    $attacker->refresh();
    $defender->refresh();

    expect($attacker->health)->toBe($defender->health); // genuinely tied
    expect($attacker->health)->toBe(40); // 50 - 10 rounds * 1 dmg each
    expect($attacker->isHospitalized())->toBeTrue(); // the loser is hospitalized
    expect($attacker->gold)->toBe(90); // loser lost 10% to the winner
    expect($defender->gold)->toBe(10);
});

// ---------------------------------------------------------------------------
// ADR-002 §4: the full lock. Busy blocks attacking, NOT being attacked.
// ---------------------------------------------------------------------------

test('a busy attacker cannot fight, and the refusal names what they are doing', function () {
    $attackerUser = User::factory()->create();
    $attacker = Character::create(['user_id' => $attackerUser->id, 'level' => 1, 'energy' => 10]);
    $defender = Character::create(['user_id' => User::factory()->create()->id]);

    app(WorkService::class)->start($attacker, Occupation::create([
        'name' => 'Grave Digger', 'description' => 'Dig.', 'min_level' => 1,
        'max_level' => 5, 'gold_per_energy' => 2,
    ]));

    expect(fn () => app(CombatService::class)->resolve($attacker->refresh(), $defender))
        ->toThrow(GameActionException::class, 'Thou canst not take up arms while at thy labours.');

    expect(CombatLog::count())->toBe(0);
});

test('a busy defender CAN still be attacked — a shift is not invulnerability', function () {
    $attackerUser = User::factory()->create();
    $attacker = Character::create([
        'user_id' => $attackerUser->id,
        'health' => 200, 'max_health' => 200, 'strength' => 1000, 'agility' => 5,
    ]);
    $defender = Character::create([
        'user_id' => User::factory()->create()->id, 'level' => 1, 'energy' => 10, 'agility' => 0,
    ]);

    app(WorkService::class)->start($defender, Occupation::create([
        'name' => 'Grave Digger', 'description' => 'Dig.', 'min_level' => 1,
        'max_level' => 5, 'gold_per_energy' => 2,
    ]));

    expect($defender->refresh()->isBusy())->toBeTrue();

    $result = app(CombatService::class)->resolve($attacker, $defender->refresh());

    expect($result->winner->id)->toBe($attacker->id);
    expect(CombatLog::count())->toBe(1);
});

test('a finished session unblocks attacking without any explicit resolve call', function () {
    $attackerUser = User::factory()->create();
    $attacker = Character::create([
        'user_id' => $attackerUser->id, 'level' => 1, 'energy' => 10,
        'health' => 200, 'max_health' => 200, 'strength' => 1000, 'agility' => 5,
    ]);
    $defender = Character::create(['user_id' => User::factory()->create()->id, 'agility' => 0]);

    app(TrainingService::class)->start($attacker, 'strength');

    $this->travel(6)->minutes();

    // resolve() resolves the pending session itself, then fights.
    $result = app(CombatService::class)->resolve($attacker->refresh(), $defender);

    expect($result->winner->id)->toBe($attacker->id);
    expect($attacker->refresh()->strength)->toEqual(1001); // the drill landed
    expect($attacker->refresh()->activity_type)->toBeNull();

    $this->travelBack();
});
