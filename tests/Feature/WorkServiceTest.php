<?php

use App\Models\Character;
use App\Models\Occupation;
use App\Models\User;
use App\Services\ActivityService;
use App\Services\GameActionException;
use App\Services\WorkService;

function worker(array $overrides = []): Character
{
    return Character::create(array_merge([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'energy' => 10,
        'max_energy' => 10,
        'gold' => 0,
        'xp' => 0,
    ], $overrides));
}

function occupation(array $overrides = []): Occupation
{
    return Occupation::create(array_merge([
        'name' => 'Grave Digger',
        'description' => 'Dig fresh plots.',
        'min_level' => 1,
        'max_level' => 5,
        'gold_per_energy' => 2,
    ], $overrides));
}

// ---------------------------------------------------------------------------
// durationFor — the ADR-002 §1 pacing table, asserted verbatim.
// ---------------------------------------------------------------------------

test('durationFor matches the signed-off pacing table', function () {
    expect(WorkService::durationFor(1))->toBe(300);    // 5m 00s
    expect(WorkService::durationFor(5))->toBe(540);    // 9m 00s
    expect(WorkService::durationFor(10))->toBe(840);   // 14m 00s
    expect(WorkService::durationFor(20))->toBe(1440);  // 24m 00s
    expect(WorkService::durationFor(50))->toBe(3240);  // 54m 00s
});

// ---------------------------------------------------------------------------
// start() — the assertions that survive from the synchronous version.
// ---------------------------------------------------------------------------

test('a qualified character starts a shift, spending all energy immediately and snapshotting the payout basis', function () {
    $character = worker(['energy' => 10]);
    $job = occupation(['gold_per_energy' => 2]);

    $result = (new WorkService)->start($character, $job);

    expect($result->type)->toBe('work');
    expect($result->completed)->toBeFalse();
    expect($result->energySpent)->toBe(10);
    expect($result->occupationName)->toBe('Grave Digger');

    $character->refresh();
    expect($character->energy)->toEqual(0);
    expect($character->gold)->toEqual(0); // not paid yet
    expect($character->activity_type)->toBe('work');
    expect($character->activity_energy_spent)->toEqual(10);
    expect($character->activity_gold_rate)->toEqual(2);
    expect($character->isBusy())->toBeTrue();
});

test('a character below the minimum level is rejected and nothing changes', function () {
    $character = worker(['level' => 1]);
    $job = occupation(['min_level' => 5, 'max_level' => 10]);

    expect(fn () => (new WorkService)->start($character, $job))
        ->toThrow(GameActionException::class, 'You are not the right level for this work.');

    $character->refresh();
    expect($character->energy)->toEqual(10);
    expect($character->activity_type)->toBeNull();
});

test('a character above the maximum level is rejected and nothing changes', function () {
    $character = worker(['level' => 20]);
    $job = occupation(['min_level' => 1, 'max_level' => 5]);

    expect(fn () => (new WorkService)->start($character, $job))
        ->toThrow(GameActionException::class, 'You are not the right level for this work.');

    $character->refresh();
    expect($character->energy)->toEqual(10);
    expect($character->activity_type)->toBeNull();
});

test('a character with zero energy is rejected and earns no free shift', function () {
    $character = worker(['energy' => 0]);

    expect(fn () => (new WorkService)->start($character, occupation()))
        ->toThrow(GameActionException::class, 'You have no energy to work.');

    $character->refresh();
    expect($character->activity_type)->toBeNull();
});

test('an occupation with a null max level accepts a high level character', function () {
    $character = worker(['level' => 50, 'energy' => 10]);
    $job = occupation(['min_level' => 15, 'max_level' => null]);

    (new WorkService)->start($character, $job);

    $character->refresh();
    expect($character->activity_type)->toBe('work');
});

test('a character exactly at the occupation minimum level can start a shift', function () {
    $character = worker(['level' => 3]);
    $job = occupation(['min_level' => 3, 'max_level' => 10]);

    (new WorkService)->start($character, $job);

    expect($character->refresh()->activity_type)->toBe('work');
});

test('a character exactly at the occupation maximum level can start a shift', function () {
    $character = worker(['level' => 10]);
    $job = occupation(['min_level' => 3, 'max_level' => 10]);

    (new WorkService)->start($character, $job);

    expect($character->refresh()->activity_type)->toBe('work');
});

test('a character one level above the occupation maximum is rejected', function () {
    $character = worker(['level' => 11]);
    $job = occupation(['min_level' => 3, 'max_level' => 10]);

    expect(fn () => (new WorkService)->start($character, $job))
        ->toThrow(GameActionException::class, 'You are not the right level for this work.');
});

test('a character one level below the occupation minimum is rejected', function () {
    $character = worker(['level' => 2]);
    $job = occupation(['min_level' => 3, 'max_level' => 10]);

    expect(fn () => (new WorkService)->start($character, $job))
        ->toThrow(GameActionException::class, 'You are not the right level for this work.');
});

// ---------------------------------------------------------------------------
// resolvePending() — gold and the XP trickle, now behind travel().
// ---------------------------------------------------------------------------

test('gold and xp are not applied before completes_at', function () {
    $character = worker(['energy' => 10]);
    (new WorkService)->start($character, occupation(['gold_per_energy' => 2]));

    $this->travel(WorkService::durationFor(1) - 1)->seconds();

    expect((new WorkService)->resolvePending($character))->toBeNull();

    $character->refresh();
    expect($character->gold)->toEqual(0);
    expect($character->xp)->toEqual(0);
    expect($character->activity_type)->toBe('work');

    $this->travelBack();
});

test('gold and the xp trickle land once completes_at has passed and the session clears', function () {
    $character = worker(['energy' => 10]);
    (new WorkService)->start($character, occupation(['gold_per_energy' => 2]));

    $this->travel(6)->minutes();

    $result = (new WorkService)->resolvePending($character);

    expect($result->completed)->toBeTrue();
    expect($result->goldEarned)->toBe(20);   // 10 energy x 2
    expect($result->xpEarned)->toBe(10);     // 10 energy x XP_PER_ENERGY
    expect($result->occupationName)->toBe('Grave Digger');

    $character->refresh();
    expect($character->gold)->toEqual(20);
    expect($character->xp)->toEqual(10);
    expect($character->activity_type)->toBeNull();
    expect($character->isBusy())->toBeFalse();

    $this->travelBack();
});

test('a shift that crosses the xp threshold levels the character up at resolution', function () {
    // Level 1 needs 100 xp; 60 energy at 1 xp each, plus 40 already banked.
    $character = worker(['energy' => 60, 'max_energy' => 60, 'xp' => 40]);
    (new WorkService)->start($character, occupation(['gold_per_energy' => 1]));

    $this->travel(6)->minutes();
    $result = (new WorkService)->resolvePending($character);

    expect($result->leveledUp)->toBeTrue();

    $character->refresh();
    expect($character->level)->toEqual(2);

    $this->travelBack();
});

// ---------------------------------------------------------------------------
// The snapshot columns must insulate the payout from mid-shift changes.
// ---------------------------------------------------------------------------

test('retuning the occupation mid-shift does not change the promised payout', function () {
    $character = worker(['energy' => 10]);
    $job = occupation(['gold_per_energy' => 2]);

    (new WorkService)->start($character, $job); // promised 10 x 2 = 20

    $job->update(['gold_per_energy' => 99]); // owner retunes the economy mid-shift

    $this->travel(6)->minutes();
    $result = (new WorkService)->resolvePending($character);

    expect($result->goldEarned)->toBe(20);
    expect($character->refresh()->gold)->toEqual(20);

    $this->travelBack();
});

test('deleting the occupation mid-shift still pays the promised gold', function () {
    $character = worker(['energy' => 10]);
    $job = occupation(['gold_per_energy' => 2]);

    (new WorkService)->start($character, $job);

    $job->delete(); // nullOnDelete clears the FK; the snapshot survives

    $this->travel(6)->minutes();
    $result = (new WorkService)->resolvePending($character);

    expect($result->goldEarned)->toBe(20);
    expect($result->occupationName)->toBeNull(); // display only, gone
    expect($character->refresh()->gold)->toEqual(20);

    $this->travelBack();
});

// ---------------------------------------------------------------------------
// The lock and idempotency.
// ---------------------------------------------------------------------------

test('a second start while busy is rejected and the first shift survives', function () {
    $character = worker(['energy' => 10]);
    (new WorkService)->start($character, occupation(['gold_per_energy' => 2]));

    expect(fn () => (new WorkService)->start($character, occupation(['name' => 'Other'])))
        ->toThrow(GameActionException::class, 'Thou art already at thy labours.');

    $character->refresh();
    expect($character->activity_gold_rate)->toEqual(2); // untouched
});

test('resolving twice pays the gold only once and the second call returns null without throwing', function () {
    $character = worker(['energy' => 10]);
    (new WorkService)->start($character, occupation(['gold_per_energy' => 2]));

    $this->travel(6)->minutes();

    $first = (new WorkService)->resolvePending($character);
    $second = (new WorkService)->resolvePending($character);

    expect($first)->not->toBeNull();
    expect($second)->toBeNull();

    $character->refresh();
    expect($character->gold)->toEqual(20); // not 40
    expect($character->xp)->toEqual(10);   // not 20

    $this->travelBack();
});

test('two near-simultaneous resolvers race for the same shift and only one pays out', function () {
    $character = worker(['energy' => 10]);
    (new WorkService)->start($character, occupation(['gold_per_energy' => 2]));

    $this->travel(6)->minutes();

    $a = Character::find($character->id);
    $b = Character::find($character->id);

    $resultA = app(ActivityService::class)->resolvePending($a);
    $resultB = app(ActivityService::class)->resolvePending($b);

    expect(collect([$resultA, $resultB])->filter()->count())->toBe(1);

    $character->refresh();
    expect($character->gold)->toEqual(20);
    expect($character->xp)->toEqual(10);

    $this->travelBack();
});

test('a completed shift resolves itself when the next start is attempted', function () {
    $character = worker(['energy' => 10]);
    $job = occupation(['gold_per_energy' => 2]);
    (new WorkService)->start($character, $job);

    $this->travel(6)->minutes();
    $character->update(['energy' => 4]); // regen ticked while the shift ran

    (new WorkService)->start($character, $job);

    $character->refresh();
    expect($character->gold)->toEqual(20);             // first shift landed
    expect($character->activity_energy_spent)->toEqual(4); // second shift running
    expect($character->energy)->toEqual(0);

    $this->travelBack();
});
