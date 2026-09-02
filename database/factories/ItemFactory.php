<?php

namespace Database\Factories;

use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    /** Upper bound on a weapon's stat deltas — unchanged by ADR-003. */
    public const WEAPON_DELTA_MAX = 10;

    /**
     * Upper bound for shield/head/body. ADR-003 §5: going from one armor slot
     * to three would otherwise triple the gear-derived defense ceiling, so the
     * non-weapon range scales to ~a third. 3 slots x 3 = 9 against the old
     * 1 slot x 10 = 10 — near-identical ceiling, different shape.
     */
    public const ARMOR_DELTA_MAX = 3;

    /**
     * The shield's mobility cost, applied to BOTH speed and dexterity: it is
     * harder to swing quickly around and harder to dodge behind. Its
     * defense_delta uses ARMOR_DELTA_MAX like any other defensive piece, so
     * the trade is mobility, not a smaller defensive payoff.
     */
    public const SHIELD_PENALTY_MIN = -3;

    public const SHIELD_PENALTY_MAX = -1;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(), // flavour only, no mechanical effect
            'faction' => null, // universal by default; faction-locked items opt in
            'min_level' => 1,
            'cost' => fake()->numberBetween(50, 500),
            'image' => null,
            ...self::deltasFor(fake()->randomElement(Item::SLOTS)),
        ];
    }

    /** Pin the slot, and with it the delta ranges — `Item::factory()->slot('shield')`. */
    public function slot(string $slot): static
    {
        return $this->state(fn () => self::deltasFor($slot));
    }

    /**
     * The slot and the four deltas move together: the range depends on the
     * slot (ADR-003 §5), so they can't be set independently.
     *
     * @return array<string, mixed>
     */
    private static function deltasFor(string $slot): array
    {
        $max = $slot === 'weapon' ? self::WEAPON_DELTA_MAX : self::ARMOR_DELTA_MAX;

        // A shield trades mobility for cover; every other slot is straight gain.
        $mobility = fn () => $slot === 'shield'
            ? fake()->numberBetween(self::SHIELD_PENALTY_MIN, self::SHIELD_PENALTY_MAX)
            : fake()->numberBetween(0, $max);

        return [
            'slot' => $slot,
            'strength_delta' => fake()->numberBetween(0, $max),
            'defense_delta' => fake()->numberBetween(0, $max),
            'speed_delta' => $mobility(),
            'dexterity_delta' => $mobility(),
        ];
    }
}
