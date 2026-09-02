<?php

use App\Livewire\Battle;
use App\Livewire\Market;
use App\Livewire\StatusBar;
use App\Livewire\Train;
use App\Livewire\Work;
use App\Models\Character;
use App\Models\Item;
use App\Models\Occupation;
use App\Models\User;
use App\Services\TrainingService;
use App\Services\WorkService;
use Livewire\Livewire;

function actor(array $overrides = []): array
{
    $user = User::factory()->create();
    $character = Character::create(array_merge([
        'user_id' => $user->id,
        'level' => 1,
        'energy' => 10,
        'max_energy' => 10,
        'gold' => 1000,
        'health' => 100,
        'max_health' => 100,
        'strength' => 5,
    ], $overrides));

    return [$user, $character];
}

function digging(): Occupation
{
    return Occupation::create([
        'name' => 'Grave Digger',
        'description' => 'Dig.',
        'min_level' => 1,
        'max_level' => 5,
        'gold_per_energy' => 2,
    ]);
}

// ---------------------------------------------------------------------------
// Starting a session through the components.
// ---------------------------------------------------------------------------

test('the work component starts a shift and flashes that it has begun, not an outcome', function () {
    [$user, $character] = actor();
    $job = digging();

    $this->actingAs($user);
    Livewire::test(Work::class)
        ->call('work', $job->id)
        ->assertSee('You begin a shift as a Grave Digger.');

    $character->refresh();
    expect($character->activity_type)->toBe('work');
    expect($character->gold)->toEqual(1000); // not paid yet
});

test('the train component starts a drill and flashes that it has begun', function () {
    [$user, $character] = actor();

    $this->actingAs($user);
    Livewire::test(Train::class)
        ->call('train', 'strength')
        ->assertSee('You begin drilling Strength.');

    $character->refresh();
    expect($character->activity_type)->toBe('train');
    expect($character->strength)->toEqual(5); // not applied yet
});

// ---------------------------------------------------------------------------
// The busy refusal has to reach the UI in V1's voice, on every locked surface.
// ---------------------------------------------------------------------------

test('attacking while busy flashes the in-character refusal, not a raw error', function () {
    [$user, $character] = actor();
    $defender = Character::create(['user_id' => User::factory()->create()->id, 'level' => 1]);

    // Mark revealed before the shift started — searching is blocked while busy,
    // but a mark already in hand still lets the player reach for Attack.
    $character->update(['opponent_id' => $defender->id]);
    app(WorkService::class)->start($character, digging());

    $this->actingAs($user);
    Livewire::test(Battle::class)
        ->call('attack')
        ->assertSee('Thou canst not take up arms while at thy labours.');
});

test('buying while busy flashes the in-character refusal, not a raw error', function () {
    [$user, $character] = actor();
    $item = Item::create([
        'name' => 'Rusty Dagger', 'slot' => 'weapon', 'strength_delta' => 2,
        'min_level' => 1, 'cost' => 50,
    ]);

    app(TrainingService::class)->start($character, 'strength');

    $this->actingAs($user);
    Livewire::test(Market::class)
        ->call('buy', $item->id)
        ->assertSee('The merchants will not serve thee while thou art at the training yard.');
    expect($character->refresh()->gold)->toEqual(1000);
});

test('starting a second session while busy flashes the in-character refusal', function () {
    [$user, $character] = actor(['energy' => 10]);

    app(TrainingService::class)->start($character, 'strength');

    $this->actingAs($user);
    Livewire::test(Train::class)
        ->call('train', 'defense')
        ->assertSee('Thou art already at the training yard. Finish before taking up another task.');
});

// ---------------------------------------------------------------------------
// Lazy resolution: the result lands on the next paint, with no click.
// ---------------------------------------------------------------------------

test('mounting the work page resolves a finished shift without any click', function () {
    [$user, $character] = actor();
    app(WorkService::class)->start($character, digging());

    $this->travel(6)->minutes();

    $this->actingAs($user);
    Livewire::test(Work::class);

    $character->refresh();
    expect($character->gold)->toEqual(1020);
    expect($character->activity_type)->toBeNull();

    $this->travelBack();
});

test('mounting the train page resolves a finished drill without any click', function () {
    [$user, $character] = actor();
    app(TrainingService::class)->start($character, 'strength');

    $this->travel(6)->minutes();

    $this->actingAs($user);
    Livewire::test(Train::class);

    $character->refresh();
    expect($character->strength)->toEqual(6);
    expect($character->activity_type)->toBeNull();

    $this->travelBack();
});

// ---------------------------------------------------------------------------
// The status-bar badge: it shows, and it clears itself once resolved.
// ---------------------------------------------------------------------------

test('busy and hospitalized badges both render, in their own row, when a character is somehow both', function () {
    // Not reachable via the game loop (ADR-002 fork 3 means a shift just runs
    // to completion), but a character CAN be attacked mid-shift, so the pair
    // must at least look sane.
    [$user, $character] = actor();
    app(WorkService::class)->start($character, digging());
    $character->update(['hospitalized_until' => now()->addMinutes(30)]);

    expect($character->refresh()->isBusy())->toBeTrue();
    expect($character->isHospitalized())->toBeTrue();

    $this->actingAs($user);

    Livewire::test(StatusBar::class)
        ->assertSee('At thy labours')          // busy badge
        ->assertSee('minutes from now')        // hospital badge
        ->assertSee('basis-full', false);      // both sit on their own wrapped row
});

test('the status bar badge clears itself when the countdown reports completion', function () {
    [$user, $character] = actor();
    app(WorkService::class)->start($character, digging());

    $this->actingAs($user);
    $component = Livewire::test(StatusBar::class)->assertSee('At thy labours');

    $this->travel(6)->minutes();

    // What <x-activity-countdown> dispatches when it hits zero.
    $component->dispatch('activity-completed')
        ->assertDontSee('At thy labours')
        ->assertDispatched('character-updated');

    $character->refresh();
    expect($character->gold)->toEqual(1020); // the countdown triggered the real resolve
    expect($character->activity_type)->toBeNull();

    $this->travelBack();
});

test('the work page and the status bar agree on whether a session is running', function () {
    [$user, $character] = actor();
    app(WorkService::class)->start($character, digging());

    $this->actingAs($user);

    // Both surfaces read the same row, so both show the lock.
    Livewire::test(StatusBar::class)->assertSee('At thy labours');
    Livewire::test(Work::class)->assertSee('Busy');

    $this->travel(6)->minutes();

    // Once resolved, neither shows it — and the page resolves on mount.
    Livewire::test(Work::class)->assertDontSee('Busy');
    Livewire::test(StatusBar::class)->assertDontSee('At thy labours');

    $this->travelBack();
});

// ---------------------------------------------------------------------------
// ADR-003 + the item description column: the four stats and the flavour text
// actually reach the rendered pages, not just the service layer.
// ---------------------------------------------------------------------------

test('the four battle stats and an item description render on the pages that show them', function () {
    [$user, $character] = actor(['defense' => 5, 'speed' => 7, 'dexterity' => 9]);

    $item = Item::create([
        'name' => 'Whispering Edge',
        'slot' => 'weapon',
        'description' => 'It hums a little, and no one agrees about the tune.',
        'strength_delta' => 3,
        'defense_delta' => -1,
        'speed_delta' => 4,
        'dexterity_delta' => 2,
        'min_level' => 1,
        'cost' => 100,
    ]);

    $this->actingAs($user);

    // Home: both new stat cells, with their values.
    Livewire::test(\App\Livewire\Home::class)
        ->assertSee('Speed')->assertSee('Dexterity')
        ->assertSee('7')->assertSee('9');

    // Train: a card for each of the four trainable stats.
    Livewire::test(Train::class)
        ->assertSee('Train Strength')->assertSee('Train Defense')
        ->assertSee('Train Speed')->assertSee('Train Dexterity');

    // Market card tier: one headline stat only. `Whispering Edge` has
    // SPD +4 as its largest-magnitude delta, so that is what the card shows —
    // the rest of the breakdown and the flavour text are popup-only now.
    $market = Livewire::test(Market::class);
    $market->assertSee('+4 SPD')
        ->assertDontSee($item->description)
        ->assertDontSee('DEX');

    // Market popup tier: the full four-stat breakdown plus the flavour text.
    $market->call('selectItem', $item->id)
        ->assertSee('STR')->assertSee('DEF')->assertSee('SPD')->assertSee('DEX')
        ->assertSee($item->description);
});
