<?php

namespace Tests\Feature\Call;

use App\Enums\Call\CallStatus;
use App\Models\Call;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CallHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_paginated_call_history_only_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $stranger = User::factory()->create();

        // 2 calls involving user
        Call::factory()->create(['caller_id' => $user->id, 'receiver_id' => $other->id]);
        Call::factory()->create(['caller_id' => $other->id, 'receiver_id' => $user->id]);

        // 1 call between strangers
        Call::factory()->create(['caller_id' => $other->id, 'receiver_id' => $stranger->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/calls');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_filters_call_history_by_status(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Call::factory()->create([
            'caller_id' => $user->id,
            'receiver_id' => $other->id,
            'status' => CallStatus::Accepted,
        ]);
        Call::factory()->create([
            'caller_id' => $user->id,
            'receiver_id' => $other->id,
            'status' => CallStatus::Missed,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/calls?status=missed');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'missed');
    }

    public function test_filters_call_history_by_date_range(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $oldCall = Call::factory()->create([
            'caller_id' => $user->id,
            'receiver_id' => $other->id,
            'created_at' => now()->subDays(10),
        ]);
        $newCall = Call::factory()->create([
            'caller_id' => $user->id,
            'receiver_id' => $other->id,
            'created_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/calls?date_from='.now()->subDays(2)->toIso8601String());

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $newCall->id);
    }

    public function test_allows_participants_to_view_call_details_and_forbids_strangers(): void
    {
        $caller = User::factory()->create();
        $receiver = User::factory()->create();
        $stranger = User::factory()->create();

        $call = Call::factory()->create([
            'caller_id' => $caller->id,
            'receiver_id' => $receiver->id,
        ]);

        Sanctum::actingAs($caller);
        $this->getJson("/api/v1/calls/{$call->id}")->assertOk()->assertJsonPath('data.id', (string) $call->id);

        Sanctum::actingAs($receiver);
        $this->getJson("/api/v1/calls/{$call->id}")->assertOk()->assertJsonPath('data.id', (string) $call->id);

        Sanctum::actingAs($stranger);
        $this->getJson("/api/v1/calls/{$call->id}")->assertForbidden();
    }
}
