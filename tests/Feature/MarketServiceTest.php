<?php

use App\Livewire\Market;
use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\Item;
use App\Models\User;
use App\Services\GameActionException;
use App\Services\MarketService;
use Livewire\Livewire;

test('buying an item with enough gold and level creates an owned row and decreases gold', function () {
    $character = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'gold' => 100,
    ]);
    $item = Item::create([
        'name' => 'Rusty Dagger',
        'type' => 'weapon',
        'strength_delta' => 2,
        'min_level' => 1,
        'cost' => 50,
    ]);

    $characterItem = (new MarketService)->buy($character, $item);

    expect($characterItem->character_id)->toEqual($character->id);
    expect($characterItem->item_id)->toEqual($item->id);
    expect($characterItem->equipped)->toBeFalse();

    $character->refresh();
    expect($character->gold)->toEqual(50);
    expect(CharacterItem::where('character_id', $character->id)->where('item_id', $item->id)->exists())->toBeTrue();
});

test('buying with insufficient gold is rejected and nothing changes', function () {
    $character = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'gold' => 10,
    ]);
    $item = Item::create([
        'name' => 'Iron Sword',
        'type' => 'weapon',
        'strength_delta' => 5,
        'min_level' => 1,
        'cost' => 200,
    ]);

    expect(fn () => (new MarketService)->buy($character, $item))
        ->toThrow(GameActionException::class, 'Not enough gold.');

    $character->refresh();
    expect($character->gold)->toEqual(10);
    expect(CharacterItem::where('character_id', $character->id)->where('item_id', $item->id)->exists())->toBeFalse();
});

test('buying an item below the required level is rejected and nothing changes', function () {
    $character = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'gold' => 1000,
    ]);
    $item = Item::create([
        'name' => 'Cursed Blade',
        'type' => 'weapon',
        'strength_delta' => 9,
        'speed_delta' => 2,
        'dexterity_delta' => 2,
        'min_level' => 8,
        'cost' => 600,
    ]);

    expect(fn () => (new MarketService)->buy($character, $item))
        ->toThrow(GameActionException::class, 'Your level is too low for this item.');

    $character->refresh();
    expect($character->gold)->toEqual(1000);
    expect(CharacterItem::where('character_id', $character->id)->where('item_id', $item->id)->exists())->toBeFalse();
});

test('buying the same item twice is rejected on the second attempt, leaving exactly one owned row and one gold deduction', function () {
    $character = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'gold' => 1000,
    ]);
    $item = Item::create([
        'name' => 'Rusty Dagger',
        'type' => 'weapon',
        'strength_delta' => 2,
        'min_level' => 1,
        'cost' => 50,
    ]);

    (new MarketService)->buy($character, $item);

    expect(fn () => (new MarketService)->buy($character, $item))
        ->toThrow(GameActionException::class, 'You already own this item.');

    $character->refresh();
    expect($character->gold)->toEqual(950);
    expect(CharacterItem::where('character_id', $character->id)->where('item_id', $item->id)->count())->toEqual(1);
});

test('equipping an item sets it equipped, swaps out a same-type item, and leaves a different-type item untouched', function () {
    $character = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 10,
        'gold' => 10000,
    ]);
    $dagger = Item::create([
        'name' => 'Rusty Dagger',
        'type' => 'weapon',
        'strength_delta' => 2,
        'min_level' => 1,
        'cost' => 50,
    ]);
    $sword = Item::create([
        'name' => 'Iron Sword',
        'type' => 'weapon',
        'strength_delta' => 5,
        'min_level' => 1,
        'cost' => 200,
    ]);
    $armor = Item::create([
        'name' => 'Leather Vest',
        'type' => 'armor',
        'defense_delta' => 2,
        'speed_delta' => 1,
        'dexterity_delta' => 1,
        'min_level' => 1,
        'cost' => 80,
    ]);

    $service = new MarketService;
    $service->buy($character, $dagger);
    $service->buy($character, $sword);
    $service->buy($character, $armor);

    $service->equip($character, $dagger);
    $service->equip($character, $armor);

    expect(CharacterItem::where('character_id', $character->id)->where('item_id', $dagger->id)->first()->equipped)->toBeTrue();
    expect(CharacterItem::where('character_id', $character->id)->where('item_id', $armor->id)->first()->equipped)->toBeTrue();

    // Equipping the sword (same type as the dagger) must unequip the dagger,
    // but leave the already-equipped armor (different type) untouched.
    $service->equip($character, $sword);

    expect(CharacterItem::where('character_id', $character->id)->where('item_id', $sword->id)->first()->equipped)->toBeTrue();
    expect(CharacterItem::where('character_id', $character->id)->where('item_id', $dagger->id)->first()->equipped)->toBeFalse();
    expect(CharacterItem::where('character_id', $character->id)->where('item_id', $armor->id)->first()->equipped)->toBeTrue();
});

test('equipping an item the character does not own is rejected', function () {
    $character = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 10,
        'gold' => 1000,
    ]);
    $item = Item::create([
        'name' => 'Iron Sword',
        'type' => 'weapon',
        'strength_delta' => 5,
        'min_level' => 1,
        'cost' => 200,
    ]);

    expect(fn () => (new MarketService)->equip($character, $item))
        ->toThrow(GameActionException::class, 'You do not own this item.');
});

test('unequipping an owned item sets equipped to false', function () {
    $character = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'gold' => 1000,
    ]);
    $item = Item::create([
        'name' => 'Rusty Dagger',
        'type' => 'weapon',
        'strength_delta' => 2,
        'min_level' => 1,
        'cost' => 50,
    ]);

    $service = new MarketService;
    $service->buy($character, $item);
    $service->equip($character, $item);
    expect(CharacterItem::where('character_id', $character->id)->where('item_id', $item->id)->first()->equipped)->toBeTrue();

    $service->unequip($character, $item);

    expect(CharacterItem::where('character_id', $character->id)->where('item_id', $item->id)->first()->equipped)->toBeFalse();
});

// --- Gap-fill: exact-gold boundary, same-type equip swap in isolation ---

test('buying an item when gold exactly equals the cost succeeds and leaves zero gold', function () {
    $character = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'gold' => 50,
    ]);
    $item = Item::create([
        'name' => 'Bone Charm',
        'type' => 'armor',
        'defense_delta' => 1,
        'min_level' => 1,
        'cost' => 50,
    ]);

    $characterItem = (new MarketService)->buy($character, $item);

    expect($characterItem->character_id)->toEqual($character->id);
    expect($characterItem->item_id)->toEqual($item->id);

    $character->refresh();
    expect($character->gold)->toEqual(0);
    expect(CharacterItem::where('character_id', $character->id)->where('item_id', $item->id)->exists())->toBeTrue();
});

test('equipping a second owned item of the same type unequips the first, even with no other items owned', function () {
    $character = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'gold' => 1000,
    ]);
    $dagger = Item::create([
        'name' => 'Rusty Dagger',
        'type' => 'weapon',
        'strength_delta' => 2,
        'min_level' => 1,
        'cost' => 50,
    ]);
    $sword = Item::create([
        'name' => 'Iron Sword',
        'type' => 'weapon',
        'strength_delta' => 5,
        'min_level' => 1,
        'cost' => 200,
    ]);

    $service = new MarketService;
    $service->buy($character, $dagger);
    $service->buy($character, $sword);

    $service->equip($character, $dagger);
    expect(CharacterItem::where('character_id', $character->id)->where('item_id', $dagger->id)->first()->equipped)->toBeTrue();

    $service->equip($character, $sword);

    expect(CharacterItem::where('character_id', $character->id)->where('item_id', $sword->id)->first()->equipped)->toBeTrue();
    expect(CharacterItem::where('character_id', $character->id)->where('item_id', $dagger->id)->first()->equipped)->toBeFalse();
});

// ---------------------------------------------------------------------------
// ADR-002 §4: the market is part of the full lock.
// ---------------------------------------------------------------------------

function busyTrader(): Character
{
    $character = Character::create([
        'user_id' => User::factory()->create()->id,
        'level' => 5,
        'gold' => 1000,
        'energy' => 10,
    ]);

    app(App\Services\WorkService::class)->start($character, App\Models\Occupation::create([
        'name' => 'Grave Digger', 'description' => 'Dig.', 'min_level' => 1,
        'max_level' => 5, 'gold_per_energy' => 2,
    ]));

    return $character->refresh();
}

test('a busy character cannot buy, and the refusal names what they are doing', function () {
    $character = busyTrader();
    $item = Item::create([
        'name' => 'Rusty Dagger', 'type' => 'weapon', 'strength_delta' => 2,
        'min_level' => 1, 'cost' => 50,
    ]);

    expect(fn () => (new MarketService)->buy($character, $item))
        ->toThrow(GameActionException::class, 'The merchants will not serve thee while thou art at thy labours.');

    expect(CharacterItem::count())->toBe(0);
    expect($character->refresh()->gold)->toEqual(1000);
});

test('a busy character cannot equip or unequip', function () {
    $character = busyTrader();
    $item = Item::create([
        'name' => 'Rusty Dagger', 'type' => 'weapon', 'strength_delta' => 2,
        'min_level' => 1, 'cost' => 50,
    ]);
    $owned = CharacterItem::create([
        'character_id' => $character->id, 'item_id' => $item->id, 'equipped' => false,
    ]);

    expect(fn () => (new MarketService)->equip($character, $item))
        ->toThrow(GameActionException::class, 'The merchants will not serve thee while thou art at thy labours.');
    expect(fn () => (new MarketService)->unequip($character, $item))
        ->toThrow(GameActionException::class, 'The merchants will not serve thee while thou art at thy labours.');

    expect($owned->refresh()->equipped)->toBeFalsy();
});

test('the market unblocks once the shift resolves, with no explicit resolve call', function () {
    $character = busyTrader();
    $item = Item::create([
        'name' => 'Rusty Dagger', 'type' => 'weapon', 'strength_delta' => 2,
        'min_level' => 1, 'cost' => 50,
    ]);

    // busyTrader() is level 5, so the shift runs 9m00s (ADR-002 pacing table).
    $this->travel(10)->minutes();

    // buy() resolves the finished shift itself, then trades.
    (new MarketService)->buy($character, $item);

    $character->refresh();
    expect($character->activity_type)->toBeNull();
    expect($character->gold)->toEqual(1000 + 20 - 50); // shift paid out, then the purchase
    expect(CharacterItem::where('character_id', $character->id)->count())->toBe(1);

    $this->travelBack();
});

test('buying an item locked to another faction is rejected and no gold is spent', function () {
    $character = Character::create([
        'user_id' => User::factory()->create()->id,
        'faction' => 'faction_1',
        'level' => 5,
        'gold' => 1000,
    ]);
    $item = Item::factory()->create(['faction' => 'faction_2', 'cost' => 50, 'min_level' => 1]);

    expect(fn () => (new MarketService)->buy($character, $item))
        ->toThrow(GameActionException::class);

    $character->refresh();
    expect($character->gold)->toEqual(1000);
    expect(CharacterItem::where('character_id', $character->id)->exists())->toBeFalse();
});

test('buying an item of the character own faction is allowed', function () {
    $character = Character::create([
        'user_id' => User::factory()->create()->id,
        'faction' => 'faction_2',
        'level' => 5,
        'gold' => 1000,
    ]);
    $item = Item::factory()->create(['faction' => 'faction_2', 'cost' => 50, 'min_level' => 1]);

    (new MarketService)->buy($character, $item);

    expect(CharacterItem::where('character_id', $character->id)->where('item_id', $item->id)->exists())->toBeTrue();
});

test('the market lists universal items plus the character own faction, and nothing else', function () {
    $user = User::factory()->create();
    Character::create(['user_id' => $user->id, 'faction' => 'faction_1', 'level' => 5, 'gold' => 1000]);

    $universal = Item::factory()->create(['faction' => null]);
    $mine = Item::factory()->create(['faction' => 'faction_1']);
    $theirs = Item::factory()->create(['faction' => 'faction_2']);

    $this->actingAs($user);
    $listed = Livewire::test(Market::class)->instance()->items()->pluck('id');

    expect($listed)->toContain($universal->id, $mine->id);
    expect($listed)->not->toContain($theirs->id);
});
