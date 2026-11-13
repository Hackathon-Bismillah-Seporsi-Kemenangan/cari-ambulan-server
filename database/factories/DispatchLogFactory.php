<?php

namespace Database\Factories;

use App\Enums\DispatchStatus;
use App\Models\DispatchLog;
use App\Models\DriverProfile;
use App\Models\EmergencyRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DispatchLog>
 */
class DispatchLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'emergency_request_id' => EmergencyRequest::factory(),
            'driver_id' => DriverProfile::factory(),
            'status' => DispatchStatus::Offered->value,
            'distance_km' => fake()->randomFloat(2, 0.5, 10),
            'offered_at' => now(),
        ];
    }
}

