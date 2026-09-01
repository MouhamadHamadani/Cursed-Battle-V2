<?php

namespace Database\Factories;

use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'type' => fake()->randomElement(['weapon', 'armor']),
            'description' => fake()->sentence(), // flavour only, no mechanical effect
            'faction' => null, // universal by default; faction-locked items opt in
            'strength_delta' => fake()->numberBetween(0, 10),
            'defense_delta' => fake()->numberBetween(0, 10),
            'speed_delta' => fake()->numberBetween(0, 10),
            'dexterity_delta' => fake()->numberBetween(0, 10),
            'min_level' => 1,
            'cost' => fake()->numberBetween(50, 500),
            'image' => null,
        ];
    }
}
