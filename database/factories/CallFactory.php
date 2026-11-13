<?php

namespace Database\Factories;

use App\Enums\Call\CallEndReason;
use App\Enums\Call\CallStatus;
use App\Models\Call;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Call>
 */
class CallFactory extends Factory
{
    protected $model = Call::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'caller_id' => User::factory(),
            'receiver_id' => User::factory(),
            'room_name' => (string) Str::uuid(),
            'call_type' => 'voice',
            'status' => CallStatus::Ringing,
            'end_reason' => null,
            'started_at' => now(),
            'answered_at' => null,
            'accepted_at' => null,
            'ended_at' => null,
            'duration_seconds' => null,
            'metadata' => null,
        ];
    }

    public function initiated(): static
    {
        return $this->state(fn () => [
            'status' => CallStatus::Initiated,
        ]);
    }

    public function ringing(): static
    {
        return $this->state(fn () => [
            'status' => CallStatus::Ringing,
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn () => [
            'status' => CallStatus::Accepted,
            'answered_at' => now()->subMinutes(2),
            'accepted_at' => now()->subMinutes(2),
        ]);
    }

    public function active(): static
    {
        return $this->accepted();
    }

    public function ended(): static
    {
        return $this->state(fn () => [
            'status' => CallStatus::Ended,
            'end_reason' => CallEndReason::CallerEnded,
            'answered_at' => now()->subMinutes(5),
            'accepted_at' => now()->subMinutes(5),
            'ended_at' => now(),
            'duration_seconds' => 300,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => CallStatus::Rejected,
            'end_reason' => CallEndReason::Rejected,
            'ended_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => CallStatus::Cancelled,
            'end_reason' => CallEndReason::CallerEnded,
            'ended_at' => now(),
        ]);
    }

    public function missed(): static
    {
        return $this->state(fn () => [
            'status' => CallStatus::Missed,
            'end_reason' => CallEndReason::Timeout,
            'ended_at' => now(),
        ]);
    }
}
