<?php

namespace Database\Factories;

use App\Models\Ambulance;
use App\Models\DriverProfile;
use App\Models\Hospital;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ambulance>
 */
class AmbulanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plate_number' => fake()->bothify('B #### ???'),
            'vehicle_type' => fake()->randomElement(['Ambulans ALS', 'Ambulans BLS']),
            'is_active' => true,
            'driver_profile_id' => DriverProfile::factory(),
            'hospital_id' => Hospital::factory(),
        ];
    }
}
