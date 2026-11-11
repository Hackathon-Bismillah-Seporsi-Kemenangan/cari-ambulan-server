<?php

namespace Database\Factories;

use App\Models\Hospital;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Hospital>
 */
class HospitalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'RS '.fake()->lastName(),
            'address' => fake()->streetAddress(),
            'emergency_phone' => fake()->numerify('021#######'),
            'latitude' => fake()->latitude(-6.25, -6.17),
            'longitude' => fake()->longitude(106.78, 106.86),
            'ambulance_entrance' => 'Gerbang '.fake()->randomElement(['A', 'B', 'C']),
        ];
    }
}
