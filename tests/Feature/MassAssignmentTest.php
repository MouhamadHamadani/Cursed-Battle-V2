<?php

use App\Models\Character;
use App\Models\CharacterItem;
use Illuminate\Database\Eloquent\MassAssignmentException;

// characters carries columns that are entirely server-decided (gold, xp,
// level, the four stats, hospitalized_until...). $fillable = ['user_id',
// 'faction'] is the guardrail against a future create()/update() call that
// passes a client array straight through.
test('mass-assigning a non-fillable column on Character throws', function () {
    Character::create(['gold' => 999999]);
})->throws(MassAssignmentException::class);

test('the two columns Character actually allows are fillable', function () {
    expect((new Character)->getFillable())->toBe(['user_id', 'faction']);
});

test('mass-assigning a non-fillable column on CharacterItem throws', function () {
    CharacterItem::create(['character_id' => 1, 'item_id' => 1, 'equipped' => false, 'created_at' => '2020-01-01']);
})->throws(MassAssignmentException::class);
