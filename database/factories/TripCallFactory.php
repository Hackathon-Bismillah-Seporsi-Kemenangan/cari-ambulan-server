<?php

namespace Database\Factories;

use App\Enums\CallStatus;
use App\Models\EmergencyRequest;
use App\Models\TripCall;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TripCall>
 */
class TripCallFactory extends Factory
{
    protected $model = TripCall::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'emergency_request_id' => EmergencyRequest::factory(),
            'caller_id' => User::factory(),
            'status' => CallStatus::Ringing->value,
            'rang_at' => now(),
        ];
    }

    public function answered(): static
    {
        return $this->state(fn () => [
            'status' => CallStatus::Answered->value,
            'answered_at' => now(),
        ]);
    }
}

