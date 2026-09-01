<?php

namespace Database\Seeders;

use App\Models\Item;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ItemSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the items table.
     *
     * Idempotent (firstOrCreate keyed on name) so it's safe to re-run.
     *
     * Note on the ADR-003 split: speed_delta and dexterity_delta both carry
     * what agility_delta used to, exactly as the migration backfilled existing
     * rows — so a freshly seeded DB and a migrated one agree. Giving items
     * asymmetric speed/dexterity is content tuning, deliberately left to the
     * owner rather than invented here.
     */
    public function run(): void
    {
        $items = [
            [
                'name' => 'Rusty Dagger',
                'type' => 'weapon',
                'description' => 'Pitted, notched, and older than the man who sold it. It still ends arguments.',
                'strength_delta' => 2,
                'defense_delta' => 0,
                'speed_delta' => 0,
                'dexterity_delta' => 0,
                'min_level' => 1,
                'cost' => 50,
                'image' => null,
            ],
            [
                'name' => 'Leather Vest',
                'type' => 'armor',
                'description' => 'Boiled hide, stitched by a hand that cared. It will turn a knife once, perhaps twice.',
                'strength_delta' => 0,
                'defense_delta' => 2,
                'speed_delta' => 1,
                'dexterity_delta' => 1,
                'min_level' => 1,
                'cost' => 80,
                'image' => null,
            ],
            [
                'name' => 'Bone Shield',
                'type' => 'armor',
                'description' => 'Lashed from ribs no scholar will name. Cold to the touch, even at noon.',
                'strength_delta' => 0,
                'defense_delta' => 4,
                'speed_delta' => 0,
                'dexterity_delta' => 0,
                'min_level' => 2,
                'cost' => 150,
                'image' => null,
            ],
            [
                'name' => 'Iron Sword',
                'type' => 'weapon',
                'description' => 'An honest blade, honestly made. No song follows it, and none is needed.',
                'strength_delta' => 5,
                'defense_delta' => 0,
                'speed_delta' => 0,
                'dexterity_delta' => 0,
                'min_level' => 3,
                'cost' => 200,
                'image' => null,
            ],
            [
                'name' => 'Plate Armor',
                'type' => 'armor',
                'description' => 'A war-smith\'s life work. You will not be hurt in it, and you will not be quick.',
                'strength_delta' => 0,
                'defense_delta' => 8,
                'speed_delta' => 0,
                'dexterity_delta' => 0,
                'min_level' => 8,
                'cost' => 500,
                'image' => null,
            ],
            [
                'name' => 'Cursed Blade',
                'type' => 'weapon',
                'description' => 'It draws itself toward flesh, and it is not particular about whose.',
                'strength_delta' => 9,
                'defense_delta' => 0,
                'speed_delta' => 2,
                'dexterity_delta' => 2,
                'min_level' => 8,
                'cost' => 600,
                'image' => null,
            ],
            // Faction-locked wares. Everything above is universal (faction NULL)
            // — a basic dagger sells to anyone.
            [
                'name' => 'Ashen Scimitar',
                'type' => 'weapon',
                'description' => 'Quenched in the grey of a burned field. Its banner forges no other like it.',
                'faction' => 'faction_1',
                'strength_delta' => 4,
                'defense_delta' => 0,
                'speed_delta' => 1,
                'dexterity_delta' => 1,
                'min_level' => 2,
                'cost' => 180,
                'image' => null,
            ],
            [
                'name' => 'Tidebound Axe',
                'type' => 'weapon',
                'description' => 'Hauled from a wreck and never fully dried. Its banner forges no other like it.',
                'faction' => 'faction_2',
                'strength_delta' => 5,
                'defense_delta' => 0,
                'speed_delta' => 0,
                'dexterity_delta' => 0,
                'min_level' => 2,
                'cost' => 180,
                'image' => null,
            ],
        ];

        foreach ($items as $item) {
            Item::firstOrCreate(
                ['name' => $item['name']],
                $item
            );
        }
    }
}
