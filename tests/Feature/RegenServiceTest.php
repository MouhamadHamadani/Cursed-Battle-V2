<?php

use App\Models\Character;
use App\Models\User;
use App\Services\RegenService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;

test('character below max regens energy and health by one tick', function () {
    $character = Character::forceCreate([
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
    $character = Character::forceCreate([
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
    $character = Character::forceCreate([
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
    $below = Character::forceCreate([
        'user_id' => User::factory()->create()->id,
        'energy' => 3,
        'max_energy' => 10,
        'health' => 50,
        'max_health' => 100,
    ]);
    $atMax = Character::forceCreate([
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

/**
 * The countdown math below. Every case passes an explicit $now — regen is a
 * global cron tick, so these are pure clock arithmetic and must not depend on
 * when the suite happens to run.
 */
test('nextTickAt targets the next five minute boundary plus the cron grace', function () {
    $at = (new RegenService)->nextTickAt(CarbonImmutable::parse('2026-09-03 10:23:31'));

    expect($at->toDateTimeString())->toBe('2026-09-03 10:26:00');
});

test('a tick that is due but not yet committed is not skipped', function () {
    // 10:25:30 is past the boundary but inside the grace: the 10:25 tick has
    // not necessarily been written yet, so it is still the next one. Reporting
    // 10:31 here would jump the countdown a whole cycle for a minute out of
    // every five.
    $at = (new RegenService)->nextTickAt(CarbonImmutable::parse('2026-09-03 10:25:30'));

    expect($at->toDateTimeString())->toBe('2026-09-03 10:26:00');
});

test('nextTickAt moves on once the grace for a boundary has expired', function () {
    $at = (new RegenService)->nextTickAt(CarbonImmutable::parse('2026-09-03 10:26:00'));

    expect($at->toDateTimeString())->toBe('2026-09-03 10:31:00');
});

test('nextTickAt is strictly in the future, so a countdown cannot fire on its first frame', function () {
    // Every second across a full cycle, including both boundaries.
    foreach (range(0, 300) as $offset) {
        $now = CarbonImmutable::parse('2026-09-03 10:25:00')->addSeconds($offset);

        expect((new RegenService)->nextTickAt($now)->greaterThan($now))->toBeTrue();
    }
});

test('a full bar has no refill countdown at all', function () {
    $character = Character::forceCreate([
        'user_id' => User::factory()->create()->id,
        'energy' => 10,
        'max_energy' => 10,
        'health' => 100,
        'max_health' => 100,
    ]);

    $service = new RegenService;

    expect($service->energyFullAt($character))->toBeNull();
    expect($service->healthFullAt($character))->toBeNull();
});

test('energyFullAt counts the ticks the gap needs, the first of them being the next tick', function () {
    $character = Character::forceCreate([
        'user_id' => User::factory()->create()->id,
        'energy' => 7,
        'max_energy' => 10,
        'health' => 100,
        'max_health' => 100,
    ]);

    // 3 energy short at 1/tick = 3 ticks. The first lands at nextTickAt, so
    // the last is two intervals after it, not three.
    $at = (new RegenService)->energyFullAt($character, CarbonImmutable::parse('2026-09-03 10:23:31'));

    expect($at->toDateTimeString())->toBe('2026-09-03 10:36:00');
});

test('healthFullAt rounds a partial tick up rather than reporting full early', function () {
    $character = Character::forceCreate([
        'user_id' => User::factory()->create()->id,
        'energy' => 10,
        'max_energy' => 10,
        // 29 short at 10/tick is 2.9 ticks — the bar is not full until the 3rd.
        'health' => 71,
        'max_health' => 100,
    ]);

    $at = (new RegenService)->healthFullAt($character, CarbonImmutable::parse('2026-09-03 10:23:31'));

    expect($at->toDateTimeString())->toBe('2026-09-03 10:36:00');
});

test('the scheduled regen command runs at the interval RegenService advertises', function () {
    // Guards the one piece of drift the countdown cannot survive: if the cron
    // in routes/console.php and TICK_MINUTES disagree, every clock in the UI
    // lies and nothing else fails.
    $regen = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command ?? '', 'game:regen-tick'));

    expect($regen)->not->toBeNull();
    expect($regen->expression)->toBe('*/'.RegenService::TICK_MINUTES.' * * * *');
});
