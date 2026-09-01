<?php

use App\Models\Character;
use App\Models\User;
use App\Services\CombatService;
use App\Services\GameActionException;
use App\Services\OpponentService;

/** A character with sane defaults; pass overrides for whatever the test cares about. */
function fighter(array $attributes = []): Character
{
    return Character::create(['user_id' => User::factory()->create()->id] + $attributes);
}

function opponents(): OpponentService
{
    return app(OpponentService::class);
}

test('the first search is free and reveals an eligible opponent', function () {
    $me = fighter(['gold' => 100]);
    $them = fighter();

    $found = opponents()->find($me);

    expect($found->id)->toEqual($them->id);
    expect($me->fresh()->gold)->toEqual(100);
    expect($me->fresh()->opponent_id)->toEqual($them->id);
    expect($me->fresh()->opponent_rerolls)->toEqual(0);
});

test('cost is zero with no mark revealed and the escalating price once there is one', function () {
    $me = fighter(['gold' => 1000]);
    fighter();
    fighter();
    fighter();

    expect(opponents()->cost($me))->toEqual(0);

    opponents()->find($me);
    expect(opponents()->cost($me->fresh()))->toEqual(10);

    opponents()->find($me->fresh());
    expect(opponents()->cost($me->fresh()))->toEqual(20);

    opponents()->find($me->fresh());
    expect(opponents()->cost($me->fresh()))->toEqual(40);
});

test('re-rolls charge the escalating price and bank the counter', function () {
    $me = fighter(['gold' => 1000]);
    fighter();
    fighter();
    fighter();

    opponents()->find($me);                    // free
    opponents()->find($me->fresh());           // 10
    opponents()->find($me->fresh());           // 20

    expect($me->fresh()->gold)->toEqual(970);
    expect($me->fresh()->opponent_rerolls)->toEqual(2);
});

test('a re-roll never hands back the mark being rejected', function () {
    $me = fighter(['gold' => 1000]);
    fighter();
    fighter();

    $first = opponents()->find($me);
    $second = opponents()->find($me->fresh());

    expect($second->id)->not->toEqual($first->id);
});

test('a re-roll with too little gold is rejected and changes nothing', function () {
    $me = fighter(['gold' => 5]);
    fighter();
    fighter();

    $revealed = opponents()->find($me);

    expect(fn () => opponents()->find($me->fresh()))
        ->toThrow(GameActionException::class, 'Not enough gold to seek another.');

    expect($me->fresh()->gold)->toEqual(5);
    expect($me->fresh()->opponent_id)->toEqual($revealed->id);
    expect($me->fresh()->opponent_rerolls)->toEqual(0);
});

test('the reveal survives a fresh read of the character - a refresh is not a free re-roll', function () {
    $me = fighter(['gold' => 100]);
    $them = fighter();

    opponents()->find($me);

    // Everything a component would have held in memory is thrown away here.
    expect(opponents()->current(Character::findOrFail($me->id))->id)->toEqual($them->id);
    expect(opponents()->cost(Character::findOrFail($me->id)))->toEqual(10);
});

test('self is never revealed', function () {
    $me = fighter(['gold' => 100]);

    expect(fn () => opponents()->find($me))
        ->toThrow(GameActionException::class, 'There is no one else to face.');

    expect($me->fresh()->opponent_id)->toBeNull();
});

test('a hospitalized character is never revealed', function () {
    $me = fighter(['gold' => 100]);
    fighter(['hospitalized_until' => now()->addMinutes(10)]);

    expect(fn () => opponents()->find($me))
        ->toThrow(GameActionException::class, 'There is no one else to face.');
});

test('a fruitless search costs nothing', function () {
    $me = fighter(['gold' => 100]);
    $them = fighter();

    opponents()->find($me);                    // free, reveals the only other character
    $them->update(['hospitalized_until' => now()->addMinutes(10)]);

    expect(fn () => opponents()->find($me->fresh()))
        ->toThrow(GameActionException::class, 'There is no one else to face.');

    expect($me->fresh()->gold)->toEqual(100);
    expect($me->fresh()->opponent_rerolls)->toEqual(0);
});

test('a mark hospitalized after being revealed clears itself and the replacement is free', function () {
    $me = fighter(['gold' => 100]);
    fighter();
    fighter();

    // Which of the two is revealed is random — hospitalize whoever it was.
    $revealed = opponents()->find($me);
    $revealed->update(['hospitalized_until' => now()->addMinutes(10)]);

    expect(opponents()->current($me->fresh()))->toBeNull();
    expect($me->fresh()->opponent_id)->toBeNull();
    expect(opponents()->cost($me->fresh()))->toEqual(0);

    opponents()->find($me->fresh());

    expect($me->fresh()->gold)->toEqual(100);
});

test('a deleted mark clears the reveal', function () {
    $me = fighter(['gold' => 100]);
    $them = fighter();

    opponents()->find($me);
    $them->delete();

    expect(opponents()->current($me->fresh()))->toBeNull();
});

test('a hospitalized character cannot search, and is not charged', function () {
    $me = fighter(['gold' => 100, 'hospitalized_until' => now()->addMinutes(10)]);
    fighter();

    expect(fn () => opponents()->find($me))
        ->toThrow(GameActionException::class, 'You are hospitalized and cannot fight.');

    expect($me->fresh()->gold)->toEqual(100);
    expect($me->fresh()->opponent_id)->toBeNull();
});

test('a busy character cannot search, and the refusal names what they are doing', function () {
    $me = fighter([
        'gold' => 100,
        'activity_type' => 'train',
        'activity_stat' => 'strength',
        'activity_completes_at' => now()->addMinutes(10),
    ]);
    fighter();

    expect(fn () => opponents()->find($me))
        ->toThrow(GameActionException::class, 'training');

    expect($me->fresh()->gold)->toEqual(100);
});

test('committing to a fight resets the search to free, win or lose', function () {
    // Attacker one-shots: defender never dodges, strength gap swamps variance.
    $me = fighter([
        'gold' => 1000, 'health' => 200, 'max_health' => 200,
        'strength' => 1000, 'defense' => 5, 'dexterity' => 5,
    ]);
    fighter(['dexterity' => 0]);
    fighter(['dexterity' => 0]);

    opponents()->find($me);                        // free
    $target = opponents()->find($me->fresh());     // paid re-roll

    expect($me->fresh()->opponent_rerolls)->toEqual(1);

    app(CombatService::class)->resolve($me->fresh(), $target);

    expect($me->fresh()->opponent_id)->toBeNull();
    expect($me->fresh()->opponent_rerolls)->toEqual(0);
    expect(opponents()->cost($me->fresh()))->toEqual(0);
});

test('losing a fight also resets the search to free', function () {
    // Mirror of the above: the defender one-shots the attacker instead.
    $me = fighter(['gold' => 1000, 'health' => 10, 'dexterity' => 0]);
    $them = fighter([
        'health' => 200, 'max_health' => 200,
        'strength' => 1000, 'defense' => 5, 'speed' => 50, // acts first: one-shots $me before it can swing
    ]);

    opponents()->find($me);
    $me->update(['opponent_rerolls' => 3]);

    $result = app(CombatService::class)->resolve($me->fresh(), $them);

    expect($result->winner->id)->toEqual($them->id);
    expect($me->fresh()->opponent_id)->toBeNull();
    expect($me->fresh()->opponent_rerolls)->toEqual(0);
});

test('the defender search state is untouched - they did not choose the fight', function () {
    $me = fighter([
        'health' => 200, 'max_health' => 200,
        'strength' => 1000, 'defense' => 5, 'dexterity' => 5,
    ]);
    $them = fighter(['dexterity' => 0]);
    $bystander = fighter();

    // The defender has a search of their own in flight.
    $them->update(['opponent_id' => $bystander->id, 'opponent_rerolls' => 2]);
    opponents()->find($me);

    app(CombatService::class)->resolve($me->fresh(), $them);

    expect($them->fresh()->opponent_id)->toEqual($bystander->id);
    expect($them->fresh()->opponent_rerolls)->toEqual(2);
});

test('the re-roll price is capped so the arithmetic stays bounded', function () {
    $me = fighter(['gold' => 100, 'opponent_rerolls' => 999]);
    $them = fighter();
    $me->update(['opponent_id' => $them->id]);

    $capped = OpponentService::REROLL_BASE * (2 ** OpponentService::REROLL_EXPONENT_CAP);

    expect(opponents()->cost($me))->toEqual($capped);
});
