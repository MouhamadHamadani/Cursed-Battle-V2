<?php

use App\Models\Character;
use App\Models\User;
use App\Services\ActivityService;
use App\Services\GameActionException;
use App\Services\TrainingService;

function trainee(array $overrides = []): Character
{
    return Character::forceCreate(array_merge([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'energy' => 10,
        'strength' => 5,
        'defense' => 5,
        'speed' => 5,
        'dexterity' => 5,
    ], $overrides));
}

// ---------------------------------------------------------------------------
// durationFor — the ADR-002 §1 pacing table, asserted verbatim.
// ---------------------------------------------------------------------------

test('durationFor matches the signed-off pacing table', function () {
    expect(TrainingService::durationFor(1))->toBe(300);    // 5m 00s
    expect(TrainingService::durationFor(5))->toBe(420);    // 7m 00s
    expect(TrainingService::durationFor(10))->toBe(570);   // 9m 30s
    expect(TrainingService::durationFor(20))->toBe(870);   // 14m 30s
    expect(TrainingService::durationFor(50))->toBe(1770);  // 29m 30s
});

// ---------------------------------------------------------------------------
// start() — the assertions that survive from the synchronous version.
// ---------------------------------------------------------------------------

test('starting a session with enough energy spends the energy immediately and records the session', function () {
    $character = trainee();

    $result = (new TrainingService)->start($character, 'strength');

    expect($result->type)->toBe('train');
    expect($result->completed)->toBeFalse();
    expect($result->stat)->toBe('strength');

    $character->refresh();
    expect($character->energy)->toEqual(5);
    expect($character->activity_type)->toBe('train');
    expect($character->activity_stat)->toBe('strength');
    expect($character->activity_energy_spent)->toEqual(TrainingService::ENERGY_COST);
    expect($character->isBusy())->toBeTrue();
});

test('starting at exactly the energy cost boundary succeeds and leaves zero energy', function () {
    $character = trainee(['energy' => TrainingService::ENERGY_COST]);

    (new TrainingService)->start($character, 'speed');

    $character->refresh();
    expect($character->energy)->toEqual(0);
    expect($character->activity_stat)->toBe('speed');
});

test('all four ADR-003 stats are trainable and each one lands on its own column', function () {
    foreach (['strength', 'defense', 'speed', 'dexterity'] as $stat) {
        $character = trainee(['energy' => TrainingService::ENERGY_COST]);

        (new TrainingService)->start($character, $stat);
        expect($character->refresh()->activity_stat)->toBe($stat);

        $this->travel(6)->minutes();
        (new TrainingService)->resolvePending($character);
        $this->travelBack();

        $character->refresh();
        expect($character->{$stat})->toEqual(6); // the drilled stat, +1

        foreach (array_diff(['strength', 'defense', 'speed', 'dexterity'], [$stat]) as $untouched) {
            expect($character->{$untouched})->toEqual(5);
        }
    }
});

test('starting one energy below the cost is rejected and nothing changes', function () {
    $character = trainee(['energy' => TrainingService::ENERGY_COST - 1]);

    expect(fn () => (new TrainingService)->start($character, 'defense'))
        ->toThrow(GameActionException::class, 'Not enough energy to train (need '.TrainingService::ENERGY_COST.').');

    $character->refresh();
    expect($character->defense)->toEqual(5);
    expect($character->energy)->toEqual(TrainingService::ENERGY_COST - 1);
    expect($character->activity_type)->toBeNull();
});

test('an invalid stat name is rejected and no column is touched, proving the whitelist blocks column injection', function () {
    $character = trainee(['gold' => 100]);

    // 'agility' is in this list on purpose: ADR-003 removed the column, and
    // the whitelist must reject the old name rather than interpolate a
    // non-existent column into the UPDATE.
    foreach (['level', 'gold', 'max_health', 'agility'] as $invalidStat) {
        expect(fn () => (new TrainingService)->start($character, $invalidStat))
            ->toThrow(GameActionException::class, 'Unknown stat.');
    }

    $character->refresh();
    expect($character->level)->toEqual(1);
    expect($character->gold)->toEqual(100);
    expect($character->max_health)->toEqual(100);
    expect($character->strength)->toEqual(5);
    expect($character->energy)->toEqual(10);
    expect($character->activity_type)->toBeNull();
});

// ---------------------------------------------------------------------------
// resolvePending() — the payout, now behind travel().
// ---------------------------------------------------------------------------

test('the stat is not applied before completes_at', function () {
    $character = trainee();
    (new TrainingService)->start($character, 'strength');

    // One second short of the level-1 duration.
    $this->travel(TrainingService::durationFor(1) - 1)->seconds();

    expect((new TrainingService)->resolvePending($character))->toBeNull();

    $character->refresh();
    expect($character->strength)->toEqual(5);
    expect($character->activity_type)->toBe('train');

    $this->travelBack();
});

test('the stat is applied once completes_at has passed and the session clears', function () {
    $character = trainee();
    (new TrainingService)->start($character, 'strength');

    $this->travel(6)->minutes();

    $result = (new TrainingService)->resolvePending($character);

    expect($result)->not->toBeNull();
    expect($result->completed)->toBeTrue();
    expect($result->stat)->toBe('strength');
    expect($result->statGain)->toBe(TrainingService::STAT_GAIN);

    $character->refresh();
    expect($character->strength)->toEqual(6);
    expect($character->activity_type)->toBeNull();
    expect($character->activity_stat)->toBeNull();
    expect($character->activity_completes_at)->toBeNull();
    expect($character->isBusy())->toBeFalse();

    $this->travelBack();
});

test('resolving one stat leaves the other stats unchanged', function () {
    $character = trainee();
    (new TrainingService)->start($character, 'strength');

    $this->travel(6)->minutes();
    (new TrainingService)->resolvePending($character);

    $character->refresh();
    expect($character->defense)->toEqual(5);
    expect($character->speed)->toEqual(5);
    expect($character->dexterity)->toEqual(5);

    $this->travelBack();
});

// ---------------------------------------------------------------------------
// The lock.
// ---------------------------------------------------------------------------

test('a second start while busy is rejected and the first session survives', function () {
    $character = trainee(['energy' => 10]);
    (new TrainingService)->start($character, 'strength');

    expect(fn () => (new TrainingService)->start($character, 'defense'))
        ->toThrow(GameActionException::class, 'Thou art already at the training yard.');

    $character->refresh();
    expect($character->activity_stat)->toBe('strength'); // untouched
    expect($character->energy)->toEqual(5);              // not charged twice
});

test('a completed session resolves itself when the next start is attempted', function () {
    $character = trainee(['energy' => 10]);
    (new TrainingService)->start($character, 'strength');

    $this->travel(6)->minutes();

    // No explicit resolve — start() resolves first, then proceeds.
    (new TrainingService)->start($character, 'defense');

    $character->refresh();
    expect($character->strength)->toEqual(6);       // first session landed
    expect($character->activity_stat)->toBe('defense'); // second session running
    expect($character->energy)->toEqual(0);

    $this->travelBack();
});

// ---------------------------------------------------------------------------
// Idempotency — the deferred write must land exactly once.
// ---------------------------------------------------------------------------

test('resolving twice applies the stat only once and the second call returns null without throwing', function () {
    $character = trainee();
    (new TrainingService)->start($character, 'strength');

    $this->travel(6)->minutes();

    $first = (new TrainingService)->resolvePending($character);
    $second = (new TrainingService)->resolvePending($character);

    expect($first)->not->toBeNull();
    expect($second)->toBeNull();

    $character->refresh();
    expect($character->strength)->toEqual(6); // +1, not +2

    $this->travelBack();
});

test('two near-simultaneous resolvers race for the same session and only one wins', function () {
    $character = trainee();
    (new TrainingService)->start($character, 'strength');

    $this->travel(6)->minutes();

    // Two independent instances, each holding its own stale model, the way two
    // concurrent requests would.
    $a = Character::find($character->id);
    $b = Character::find($character->id);

    $resultA = app(ActivityService::class)->resolvePending($a);
    $resultB = app(ActivityService::class)->resolvePending($b);

    expect([$resultA, $resultB])->toContain(null);
    expect(collect([$resultA, $resultB])->filter()->count())->toBe(1);

    $character->refresh();
    expect($character->strength)->toEqual(6);
    expect($character->activity_type)->toBeNull();

    $this->travelBack();
});

test('resolvePending on an idle character is a no-op that returns null', function () {
    $character = trainee();

    expect((new TrainingService)->resolvePending($character))->toBeNull();
    expect(app(ActivityService::class)->resolvePending($character))->toBeNull();

    $character->refresh();
    expect($character->strength)->toEqual(5);
    expect($character->energy)->toEqual(10);
});
