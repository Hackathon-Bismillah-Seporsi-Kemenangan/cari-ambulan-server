<?php

namespace Database\Factories;

use App\Enums\EmergencyStatus;
use App\Models\EmergencyRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmergencyRequest>
 */
class EmergencyRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'request_code' => EmergencyRequest::generateRequestCode(),
            'is_for_self' => true,
            'emergency_type' => fake()->randomElement(['Kecelakaan', 'Henti Jantung', 'Kehamilan', 'Sesak Nafas']),
            'severity_level' => fake()->randomElement(['high', 'medium', 'low']),
            'patient_notes' => fake()->sentence(),
            'pickup_latitude' => fake()->latitude(-6.25, -6.17),
            'pickup_longitude' => fake()->longitude(106.78, 106.86),
            'pickup_address' => fake()->streetAddress(),
            'status' => EmergencyStatus::SearchingDriver->value,
            'user_id' => User::factory(),
            'request_at' => now(),
        ];
    }
}

