<?php

namespace Database\Factories;

use App\Enums\DriverAvailability;
use App\Models\Ambulance;
use App\Models\DriverProfile;
use App\Models\Hospital;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DriverProfile>
 */
class DriverProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'license_number' => fake()->bothify('SIM-########'),
            'availability_status' => DriverAvailability::Offline->value,
            'user_id' => User::factory()->driver(),
            'hospital_id' => Hospital::factory(),
        ];
    }

    /**
     * Online at the given position, with an ambulance, ready for offers.
     */
    public function onlineAt(float $latitude, float $longitude): static
    {
        return $this->state(fn (array $attributes) => [
            'availability_status' => DriverAvailability::Online->value,
            'current_latitude' => $latitude,
            'current_longitude' => $longitude,
            'location_updated_at' => now(),
        ])->withAmbulance();
    }

    public function busy(): static
    {
        return $this->state(fn (array $attributes) => [
            'availability_status' => DriverAvailability::Busy->value,
        ])->withAmbulance();
    }

    public function withAmbulance(): static
    {
        return $this->afterCreating(function ($driver) {
            Ambulance::factory()->create([
                'driver_profile_id' => $driver->id,
                'hospital_id' => $driver->hospital_id,
            ]);
        });
    }
}
