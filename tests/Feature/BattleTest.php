<?php

use App\Livewire\Battle;
use App\Models\Character;
use App\Models\CombatLog;
use App\Models\User;
use App\Services\OpponentService;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

// Determinism strategy: force stats so the attacker's very first swing is a
// guaranteed one-shot KO (defender dexterity 0 -> never dodges; strength gap
// swamps the ± level variance) so the outcome is deterministic even though
// the component resolves via the app's real, unseeded secure RNG.
test('attacking the revealed mark resolves the fight, flashes no error, logs the combat, and conserves gold between the two characters', function () {
    $attackerUser = User::factory()->create();
    $attacker = Character::forceCreate([
        'user_id' => $attackerUser->id,
        'level' => 1,
        'gold' => 50,
        'health' => 200,
        'max_health' => 200,
        'strength' => 1000,
        'defense' => 5,
        'dexterity' => 5,
    ]);

    $defenderUser = User::factory()->create();
    $defender = Character::forceCreate([
        'user_id' => $defenderUser->id,
        'level' => 1,
        'gold' => 100,
        'health' => 100,
        'max_health' => 100,
        'strength' => 5,
        'defense' => 5,
        'dexterity' => 0,
    ]);
    $goldBefore = $attacker->gold + $defender->gold;

    $this->actingAs($attackerUser);
    $component = Livewire::test(Battle::class)
        ->call('search')     // free, and $defender is the only candidate
        ->call('attack')
        ->assertDontSee('Not enough gold');

    expect($component->get('lastFight'))->not->toBeEmpty();
    expect($component->get('lastFight.knockout'))->toBeTrue();
    expect(CombatLog::count())->toBe(1);

    $goldAfter = $attacker->fresh()->gold + $defender->fresh()->gold;
    expect($goldAfter)->toBe($goldBefore);
});

test('GET /battle returns 200 for an authenticated user with a character', function () {
    $user = User::factory()->create();
    $user->character()->create([]);

    $response = $this->actingAs($user)->get('/battle');

    $response->assertStatus(200);
});

test('the page opens with no mark and a free search', function () {
    $user = User::factory()->create();
    Character::forceCreate(['user_id' => $user->id]);
    Character::forceCreate(['user_id' => User::factory()->create()->id]);

    $this->actingAs($user);
    Livewire::test(Battle::class)
        ->assertSet('showResult', false)
        ->assertSee('No mark before thee')
        ->assertSee('Seek an Opponent');
})->group('reveal');

test('searching reveals exactly one opponent, and it is neither self nor a hospitalized character', function () {
    $user = User::factory()->create();
    $me = Character::forceCreate(['user_id' => $user->id]);
    $hospitalized = Character::forceCreate([
        'user_id' => User::factory()->create()->id,
        'hospitalized_until' => now()->addMinutes(10),
    ]);
    $healthy = Character::forceCreate(['user_id' => User::factory()->create()->id]);

    $this->actingAs($user);
    $component = Livewire::test(Battle::class)->call('search');

    expect($component->get('opponent')->id)->toEqual($healthy->id);
    expect($component->get('opponent')->id)->not->toEqual($me->id);
    expect($component->get('opponent')->id)->not->toEqual($hospitalized->id);
});

test('the revealed mark and its climbing price are shown on the page', function () {
    $user = User::factory()->create();
    Character::forceCreate(['user_id' => $user->id, 'gold' => 500]);
    Character::forceCreate(['user_id' => User::factory()->create()->id]);
    Character::forceCreate(['user_id' => User::factory()->create()->id]);

    $this->actingAs($user);
    $component = Livewire::test(Battle::class)->call('search');

    // Which of the two candidates was revealed is random by design — assert
    // against whoever it actually was.
    $component->assertSee($component->get('opponent')->user->name)
        ->assertSee('Seek Another')
        ->assertSet('searchCost', OpponentService::REROLL_BASE);

    $component->call('search')->assertSet('searchCost', OpponentService::REROLL_BASE * 2);
});

test('a re-roll the character cannot afford flashes an error and takes no gold', function () {
    $user = User::factory()->create();
    Character::forceCreate(['user_id' => $user->id, 'gold' => 1]);
    Character::forceCreate(['user_id' => User::factory()->create()->id]);
    Character::forceCreate(['user_id' => User::factory()->create()->id]);

    $this->actingAs($user);
    Livewire::test(Battle::class)
        ->call('search')
        ->call('search')
        ->assertSee('Not enough gold to seek another.');

    expect(auth()->user()->character->gold)->toEqual(1);
});

test('attacking with no mark revealed is refused and resolves nothing', function () {
    $user = User::factory()->create();
    Character::forceCreate(['user_id' => $user->id]);
    Character::forceCreate(['user_id' => User::factory()->create()->id]);

    $this->actingAs($user);
    $component = Livewire::test(Battle::class)
        ->call('attack')
        ->assertSee('Seek a mark before thou swingest.');

    expect($component->get('lastFight'))->toBeEmpty();
    expect(CombatLog::count())->toBe(0);
});

// The result modal's open state is entangled to $showResult (see
// resources/views/components/dark-modal.blade.php), so a fight must both
// produce a result and open the modal, and a failed attack must not.
test('a resolved fight opens the result modal, renders the outcome banner, and clears the mark', function () {
    $attackerUser = User::factory()->create();
    Character::forceCreate([
        'user_id' => $attackerUser->id,
        'health' => 200,
        'max_health' => 200,
        'strength' => 1000,
        'dexterity' => 5,
    ]);

    Character::forceCreate([
        'user_id' => User::factory()->create()->id,
        'dexterity' => 0,
    ]);

    $this->actingAs($attackerUser);
    $component = Livewire::test(Battle::class)->call('search')->call('attack');

    expect($component->get('showResult'))->toBeTrue();
    $component->assertSee('Victory')->assertSee('Battle Result');

    // Committing ended the search: back to no mark, and free again.
    expect($component->get('opponent'))->toBeNull();
    expect($component->get('searchCost'))->toEqual(0);
});

test('the result modal stays closed when the attack is rejected', function () {
    $user = User::factory()->create();
    $me = Character::forceCreate(['user_id' => $user->id]);
    $defender = Character::forceCreate(['user_id' => User::factory()->create()->id]);

    // Revealed while healthy, then hospitalized before the swing lands.
    $me->forceFill(['opponent_id' => $defender->id])->save();
    $me->forceFill(['hospitalized_until' => now()->addMinutes(10)])->save();

    $this->actingAs($user);
    $component = Livewire::test(Battle::class)
        ->call('attack')
        ->assertSee('You are hospitalized and cannot fight.');

    expect($component->get('showResult'))->toBeFalse();
    expect($component->get('lastFight'))->toBeEmpty();
});

test('gold spent on a re-roll is reflected on the page in the same request', function () {
    $user = User::factory()->create();
    Character::forceCreate(['user_id' => $user->id, 'gold' => 500]);
    Character::forceCreate(['user_id' => User::factory()->create()->id]);
    Character::forceCreate(['user_id' => User::factory()->create()->id]);

    $this->actingAs($user);
    Livewire::test(Battle::class)
        ->call('search')   // free
        ->call('search')   // 10
        ->assertSee('490');
});

test('rendering the battle page never writes to the character row, even when the revealed mark has gone stale', function () {
    // current() is called twice per render (the opponent and searchCost
    // computeds), so clearing a hospitalized mark from inside it turned a plain
    // GET into two UPDATEs. The reveal is invalidated by *reading* it as
    // unusable, not by writing the row: find() overwrites opponent_id on its
    // next success and a won fight clears it in persistOutcome, so nothing
    // needs the write.
    $user = User::factory()->create();
    $me = Character::forceCreate(['user_id' => $user->id, 'gold' => 100]);
    $mark = Character::forceCreate([
        'user_id' => User::factory()->create()->id,
        'hospitalized_until' => now()->addMinutes(10),
    ]);
    $me->forceFill(['opponent_id' => $mark->id])->save();

    $writes = 0;
    DB::listen(function ($query) use (&$writes) {
        if (str_starts_with(strtolower(ltrim($query->sql)), 'update')) {
            $writes++;
        }
    });

    $this->actingAs($user);
    Livewire::test(Battle::class)
        ->assertSee('No mark before thee')   // the stale mark reads as absent
        ->assertSee('Seek an Opponent');

    expect($writes)->toBe(0);
    expect($me->fresh()->opponent_id)->toEqual($mark->id); // untouched by a read
})->group('reveal');
