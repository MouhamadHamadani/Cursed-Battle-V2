<?php

use App\Livewire\Market;
use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\Item;
use App\Models\Occupation;
use App\Models\User;
use App\Services\CombatService;
use App\Services\GameActionException;
use App\Services\MarketService;
use App\Services\WorkService;
use Database\Factories\ItemFactory;
use Livewire\Livewire;

test('buying an item with enough gold and level creates an owned row and decreases gold', function () {
    $character = Character::forceCreate([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'gold' => 100,
    ]);
    $item = Item::create([
        'name' => 'Rusty Dagger',
        'slot' => 'weapon',
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
    $character = Character::forceCreate([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'gold' => 10,
    ]);
    $item = Item::create([
        'name' => 'Iron Sword',
        'slot' => 'weapon',
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
    $character = Character::forceCreate([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'gold' => 1000,
    ]);
    $item = Item::create([
        'name' => 'Cursed Blade',
        'slot' => 'weapon',
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
    $character = Character::forceCreate([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'gold' => 1000,
    ]);
    $item = Item::create([
        'name' => 'Rusty Dagger',
        'slot' => 'weapon',
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

test('equipping an item sets it equipped, swaps out the same-slot occupant, and leaves another slot untouched', function () {
    $character = Character::forceCreate([
        'user_id' => User::factory()->create()->id,
        'level' => 10,
        'gold' => 10000,
    ]);
    $dagger = Item::create([
        'name' => 'Rusty Dagger',
        'slot' => 'weapon',
        'strength_delta' => 2,
        'min_level' => 1,
        'cost' => 50,
    ]);
    $sword = Item::create([
        'name' => 'Iron Sword',
        'slot' => 'weapon',
        'strength_delta' => 5,
        'min_level' => 1,
        'cost' => 200,
    ]);
    $armor = Item::create([
        'name' => 'Leather Vest',
        'slot' => 'body',
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
    $character = Character::forceCreate([
        'user_id' => User::factory()->create()->id,
        'level' => 10,
        'gold' => 1000,
    ]);
    $item = Item::create([
        'name' => 'Iron Sword',
        'slot' => 'weapon',
        'strength_delta' => 5,
        'min_level' => 1,
        'cost' => 200,
    ]);

    expect(fn () => (new MarketService)->equip($character, $item))
        ->toThrow(GameActionException::class, 'You do not own this item.');
});

test('unequipping an owned item sets equipped to false', function () {
    $character = Character::forceCreate([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'gold' => 1000,
    ]);
    $item = Item::create([
        'name' => 'Rusty Dagger',
        'slot' => 'weapon',
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

// --- Gap-fill: exact-gold boundary, same-slot equip swap in isolation ---

test('buying an item when gold exactly equals the cost succeeds and leaves zero gold', function () {
    $character = Character::forceCreate([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'gold' => 50,
    ]);
    $item = Item::create([
        'name' => 'Bone Charm',
        'slot' => 'body',
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

test('equipping a second owned item in the same slot unequips the first, even with no other items owned', function () {
    $character = Character::forceCreate([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'gold' => 1000,
    ]);
    $dagger = Item::create([
        'name' => 'Rusty Dagger',
        'slot' => 'weapon',
        'strength_delta' => 2,
        'min_level' => 1,
        'cost' => 50,
    ]);
    $sword = Item::create([
        'name' => 'Iron Sword',
        'slot' => 'weapon',
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
    $character = Character::forceCreate([
        'user_id' => User::factory()->create()->id,
        'level' => 5,
        'gold' => 1000,
        'energy' => 10,
    ]);

    app(WorkService::class)->start($character, Occupation::create([
        'name' => 'Grave Digger', 'description' => 'Dig.', 'min_level' => 1,
        'max_level' => 5, 'gold_per_energy' => 2,
    ]));

    return $character->refresh();
}

test('a busy character cannot buy, and the refusal names what they are doing', function () {
    $character = busyTrader();
    $item = Item::create([
        'name' => 'Rusty Dagger', 'slot' => 'weapon', 'strength_delta' => 2,
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
        'name' => 'Rusty Dagger', 'slot' => 'weapon', 'strength_delta' => 2,
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
        'name' => 'Rusty Dagger', 'slot' => 'weapon', 'strength_delta' => 2,
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
    $character = Character::forceCreate([
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
    $character = Character::forceCreate([
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
    Character::forceCreate(['user_id' => $user->id, 'faction' => 'faction_1', 'level' => 5, 'gold' => 1000]);

    $universal = Item::factory()->create(['faction' => null]);
    $mine = Item::factory()->create(['faction' => 'faction_1']);
    $theirs = Item::factory()->create(['faction' => 'faction_2']);

    $this->actingAs($user);
    $listed = Livewire::test(Market::class)->instance()->items()->pluck('id');

    expect($listed)->toContain($universal->id, $mine->id);
    expect($listed)->not->toContain($theirs->id);
});

// ---------------------------------------------------------------------------
// ADR-003 §4: four slots. equip() is keyed on `slot`, so the one-per-slot rule
// generalises from 2 slots to 4 without any other logic change.
// ---------------------------------------------------------------------------

function slotted(string $slot, string $name): Item
{
    return Item::create([
        'name' => $name,
        'slot' => $slot,
        'defense_delta' => 1,
        'min_level' => 1,
        'cost' => 10,
    ]);
}

test('all four slots equip independently — filling one never unequips another', function () {
    $character = Character::forceCreate([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'gold' => 1000,
    ]);

    $service = new MarketService;
    $items = [];

    foreach (Item::SLOTS as $slot) {
        $items[$slot] = slotted($slot, "Test {$slot}");
        $service->buy($character, $items[$slot]);
        $service->equip($character, $items[$slot]);
    }

    // Every slot is still equipped after all four have been filled.
    foreach (Item::SLOTS as $slot) {
        expect(CharacterItem::where('character_id', $character->id)
            ->where('item_id', $items[$slot]->id)->first()->equipped)
            ->toBeTrue("slot {$slot} should still be equipped");
    }

    expect(CharacterItem::where('character_id', $character->id)->where('equipped', true)->count())
        ->toBe(count(Item::SLOTS));
});

test('equipping a second item in a non-weapon slot unequips only that slot occupant', function () {
    $character = Character::forceCreate([
        'user_id' => User::factory()->create()->id,
        'level' => 1,
        'gold' => 1000,
    ]);

    $sword = slotted('weapon', 'Iron Sword');
    $buckler = slotted('shield', 'Oaken Buckler');
    $tower = slotted('shield', 'Tower Shield');

    $service = new MarketService;
    foreach ([$sword, $buckler, $tower] as $item) {
        $service->buy($character, $item);
    }

    $service->equip($character, $sword);
    $service->equip($character, $buckler);
    $service->equip($character, $tower);

    $equipped = fn (Item $i) => CharacterItem::where('character_id', $character->id)
        ->where('item_id', $i->id)->first()->equipped;

    expect($equipped($tower))->toBeTrue();
    expect($equipped($buckler))->toBeFalse(); // displaced by the other shield
    expect($equipped($sword))->toBeTrue();    // a different slot — untouched
});

test('the factory honours the ADR-003 delta budgets, including the shield mobility cost', function () {
    // Weapons keep the full range; the three defensive slots are scaled to a
    // third so 3 slots do not triple the gear ceiling.
    foreach (Item::factory()->count(30)->slot('weapon')->create() as $weapon) {
        expect($weapon->strength_delta)->toBeGreaterThanOrEqual(0)
            ->and($weapon->strength_delta)->toBeLessThanOrEqual(ItemFactory::WEAPON_DELTA_MAX);
    }

    foreach (['head', 'body'] as $slot) {
        foreach (Item::factory()->count(20)->slot($slot)->create() as $armor) {
            expect($armor->defense_delta)->toBeLessThanOrEqual(ItemFactory::ARMOR_DELTA_MAX);
            expect($armor->speed_delta)->toBeGreaterThanOrEqual(0); // only shields go negative
        }
    }

    foreach (Item::factory()->count(30)->slot('shield')->create() as $shield) {
        expect($shield->defense_delta)->toBeLessThanOrEqual(ItemFactory::ARMOR_DELTA_MAX);
        // Bulkier: harder to swing fast AND harder to dodge behind — both, not either.
        expect($shield->speed_delta)->toBeGreaterThanOrEqual(ItemFactory::SHIELD_PENALTY_MIN)
            ->and($shield->speed_delta)->toBeLessThanOrEqual(ItemFactory::SHIELD_PENALTY_MAX);
        expect($shield->dexterity_delta)->toBeGreaterThanOrEqual(ItemFactory::SHIELD_PENALTY_MIN)
            ->and($shield->dexterity_delta)->toBeLessThanOrEqual(ItemFactory::SHIELD_PENALTY_MAX);
    }

    // The whole point of the rebalance: 3 defensive slots at max ~= 1 old slot at max.
    expect(3 * ItemFactory::ARMOR_DELTA_MAX)->toBeLessThanOrEqual(ItemFactory::WEAPON_DELTA_MAX);
});

// ---------------------------------------------------------------------------
// The "more details" popup — an extra entry point, not a replacement for the
// on-card Buy button.
// ---------------------------------------------------------------------------

test('the details popup opens with the full item breakdown and the action that applies', function () {
    $user = User::factory()->create();
    $character = Character::forceCreate(['user_id' => $user->id, 'level' => 5, 'gold' => 1000]);

    $shield = Item::create([
        'name' => 'Bone Shield',
        'slot' => 'shield',
        'description' => 'Cold to the touch, even at noon.',
        'defense_delta' => 3,
        'speed_delta' => -2,
        'dexterity_delta' => -1,
        'min_level' => 2,
        'cost' => 150,
    ]);

    $this->actingAs($user);
    $component = Livewire::test(Market::class);

    // Closed until something is selected.
    expect($component->get('showDetails'))->toBeFalse();

    $component->call('selectItem', $shield->id);

    expect($component->get('showDetails'))->toBeTrue();
    expect($component->get('selectedItemId'))->toEqual($shield->id);

    // Slot, description, all four deltas (zeroes included), and Buy while unowned.
    $component->assertSee('shield')
        ->assertSee($shield->description)
        ->assertSee('STR')->assertSee('DEF')->assertSee('SPD')->assertSee('DEX')
        ->assertSee('Buy');

    // Buying from inside the popup flips the action to Equip, then to Unequip.
    $component->call('buy', $shield->id)->assertSee('Equip');
    $component->call('equip', $shield->id)->assertSee('Unequip');

    expect(CharacterItem::where('character_id', $character->id)
        ->where('item_id', $shield->id)->first()->equipped)->toBeTrue();
});

test('the details popup refuses an item the character faction cannot see', function () {
    $user = User::factory()->create();
    Character::forceCreate(['user_id' => $user->id, 'faction' => 'faction_1', 'level' => 5, 'gold' => 1000]);

    $theirs = Item::factory()->create(['faction' => 'faction_2']);

    $this->actingAs($user);
    $component = Livewire::test(Market::class)->call('selectItem', $theirs->id);

    // The listing scope is re-applied, so an out-of-faction id opens nothing.
    expect($component->get('selectedItemId'))->toBeNull();
    expect($component->get('showDetails'))->toBeFalse();
});

test('equipping an item that is already equipped leaves it equipped rather than silently stripping it', function () {
    // equip() is a public Livewire action, so the item id is client-chosen even
    // though the Blade renders Unequip (not Equip) for an equipped item. The
    // slot-wide mass-unequip below includes the row being equipped, and the
    // Eloquent re-equip that followed it no-oped: the in-memory model still
    // held equipped => true, so `equipped` was never dirty and no UPDATE was
    // issued. Net effect was a silent unequip that reported success.
    $character = Character::forceCreate([
        'user_id' => User::factory()->create()->id,
        'level' => 10,
        'gold' => 10000,
    ]);
    $sword = Item::create([
        'name' => 'Iron Sword',
        'slot' => 'weapon',
        'strength_delta' => 5,
        'min_level' => 1,
        'cost' => 200,
    ]);

    $service = new MarketService;
    $service->buy($character, $sword);
    $service->equip($character, $sword);
    $service->equip($character, $sword); // the replayed action

    $owned = CharacterItem::where('character_id', $character->id)->where('item_id', $sword->id)->first();
    expect($owned->equipped)->toBeTrue();

    // The deltas still count — that is what the silent unequip actually cost.
    expect((new CombatService)->effectiveStats($character->refresh())['strength'])
        ->toBe($character->strength + 5);
});
