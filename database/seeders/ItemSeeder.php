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
     */
    public function run(): void
    {
        $items = [
            [
                'name' => 'Rusty Dagger',
                'type' => 'weapon',
                'strength_delta' => 2,
                'defense_delta' => 0,
                'agility_delta' => 0,
                'min_level' => 1,
                'cost' => 50,
                'image' => null,
            ],
            [
                'name' => 'Leather Vest',
                'type' => 'armor',
                'strength_delta' => 0,
                'defense_delta' => 2,
                'agility_delta' => 1,
                'min_level' => 1,
                'cost' => 80,
                'image' => null,
            ],
            [
                'name' => 'Bone Shield',
                'type' => 'armor',
                'strength_delta' => 0,
                'defense_delta' => 4,
                'agility_delta' => 0,
                'min_level' => 2,
                'cost' => 150,
                'image' => null,
            ],
            [
                'name' => 'Iron Sword',
                'type' => 'weapon',
                'strength_delta' => 5,
                'defense_delta' => 0,
                'agility_delta' => 0,
                'min_level' => 3,
                'cost' => 200,
                'image' => null,
            ],
            [
                'name' => 'Plate Armor',
                'type' => 'armor',
                'strength_delta' => 0,
                'defense_delta' => 8,
                'agility_delta' => 0,
                'min_level' => 8,
                'cost' => 500,
                'image' => null,
            ],
            [
                'name' => 'Cursed Blade',
                'type' => 'weapon',
                'strength_delta' => 9,
                'defense_delta' => 0,
                'agility_delta' => 2,
                'min_level' => 8,
                'cost' => 600,
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
