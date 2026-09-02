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
     * Idempotent, keyed on name. `updateOrCreate` rather than `firstOrCreate`
     * (ADR-003 §7): the type→slot migration maps every armor row to `body`,
     * so re-seeding is what moves the Bone Shield into its real slot and keeps
     * a migrated DB in agreement with a freshly seeded one. These rows are
     * canonical content — there is nothing hand-tuned here to preserve.
     *
     * Delta budgets follow ADR-003 §5: weapons up to 10, the three defensive
     * slots up to 3 each, and the shield pays for its cover in mobility.
     */
    public function run(): void
    {
        $items = [
            // --- Weapons: the full delta range. ---
            [
                'name' => 'Rusty Dagger',
                'slot' => 'weapon',
                'description' => 'Pitted, notched, and older than the man who sold it. It still ends arguments.',
                'strength_delta' => 2,
                'defense_delta' => 0,
                'speed_delta' => 1,
                'dexterity_delta' => 0,
                'min_level' => 1,
                'cost' => 50,
                'image' => null,
            ],
            [
                'name' => 'Iron Sword',
                'slot' => 'weapon',
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
                'name' => 'Cursed Blade',
                'slot' => 'weapon',
                'description' => 'It draws itself toward flesh, and it is not particular about whose.',
                'strength_delta' => 9,
                'defense_delta' => 0,
                'speed_delta' => 2,
                'dexterity_delta' => 0,
                'min_level' => 8,
                'cost' => 600,
                'image' => null,
            ],

            // --- Shields: cover bought with mobility. ---
            [
                'name' => 'Bone Shield',
                'slot' => 'shield',
                'description' => 'Lashed from ribs no scholar will name. Cold to the touch, even at noon.',
                'strength_delta' => 0,
                'defense_delta' => 3,
                'speed_delta' => -2,
                'dexterity_delta' => -1,
                'min_level' => 2,
                'cost' => 150,
                'image' => null,
            ],
            [
                'name' => 'Oaken Buckler',
                'slot' => 'shield',
                'description' => 'Small, banded, and quick to bring up. It asks less of the arm than it gives back.',
                'strength_delta' => 0,
                'defense_delta' => 1,
                'speed_delta' => -1,
                'dexterity_delta' => -1,
                'min_level' => 1,
                'cost' => 70,
                'image' => null,
            ],

            // --- Head. ---
            [
                'name' => 'Padded Coif',
                'slot' => 'head',
                'description' => 'Quilted linen, sweat-stiff. It will not stop a poleaxe, but it stops the rest.',
                'strength_delta' => 0,
                'defense_delta' => 1,
                'speed_delta' => 0,
                'dexterity_delta' => 0,
                'min_level' => 1,
                'cost' => 60,
                'image' => null,
            ],
            [
                'name' => 'Grave Warden Helm',
                'slot' => 'head',
                'description' => 'The visor is fixed shut. Whoever wore it last did not need to see out.',
                'strength_delta' => 0,
                'defense_delta' => 3,
                'speed_delta' => 0,
                'dexterity_delta' => -1,
                'min_level' => 6,
                'cost' => 320,
                'image' => null,
            ],

            // --- Body: the full worn suit. ---
            [
                'name' => 'Leather Vest',
                'slot' => 'body',
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
                'name' => 'Plate Armor',
                'slot' => 'body',
                'description' => 'A war-smith\'s life work. You will not be hurt in it, and you will not be quick.',
                'strength_delta' => 0,
                'defense_delta' => 3,
                'speed_delta' => -1,
                'dexterity_delta' => -1,
                'min_level' => 8,
                'cost' => 500,
                'image' => null,
            ],

            // Faction-locked wares. Everything above is universal (faction NULL)
            // — a basic dagger sells to anyone.
            [
                'name' => 'Ashen Scimitar',
                'slot' => 'weapon',
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
                'slot' => 'weapon',
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
            Item::updateOrCreate(
                ['name' => $item['name']],
                $item
            );
        }
    }
}
