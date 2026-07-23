<?php

namespace Database\Seeders;

use App\Models\Occupation;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OccupationSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the occupations table.
     *
     * Idempotent (firstOrCreate keyed on name) so it's safe to re-run.
     */
    public function run(): void
    {
        $occupations = [
            [
                'name' => 'Grave Digger',
                'description' => 'Dig fresh plots for the recently deceased. Low pay, low risk.',
                'min_level' => 1,
                'max_level' => 5,
                'gold_per_energy' => 2,
            ],
            [
                'name' => 'Cursed Courier',
                'description' => 'Deliver hexed parcels between the districts. Don\'t open them.',
                'min_level' => 3,
                'max_level' => 10,
                'gold_per_energy' => 4,
            ],
            [
                'name' => 'Bone Merchant',
                'description' => 'Trade in relics and remains for the city\'s alchemists.',
                'min_level' => 8,
                'max_level' => 20,
                'gold_per_energy' => 7,
            ],
            [
                'name' => 'Soul Broker',
                'description' => 'Broker deals for the desperate and the damned. No upper limit to the work.',
                'min_level' => 15,
                'max_level' => null,
                'gold_per_energy' => 12,
            ],
        ];

        foreach ($occupations as $occupation) {
            Occupation::firstOrCreate(
                ['name' => $occupation['name']],
                $occupation
            );
        }
    }
}
