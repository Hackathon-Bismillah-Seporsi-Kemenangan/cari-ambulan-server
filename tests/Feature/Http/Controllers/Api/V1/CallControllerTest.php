<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Enums\Call\CallStatus;
use App\Enums\CallStatus as LegacyCallStatus;
use App\Enums\DriverAvailability;
use App\Enums\EmergencyStatus;
use App\Events\Call\CallAccepted;
use App\Events\Call\CallEnded;
use App\Events\Call\CallIncoming;
use App\Events\Call\CallRejected;
use App\Models\Call;
use App\Models\DriverProfile;
use App\Models\EmergencyRequest;
use App\Models\TripCall;
use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CallControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'livekit.url' => 'wss://example.livekit.cloud',
            'livekit.api_key' => 'devkey1234567890',
            'livekit.api_secret' => 'supersecretkey_must_be_32_characters_long_min',
            'livekit.token_ttl' => 3600,
            'services.livekit.url' => 'wss://example.livekit.cloud',
            'services.livekit.api_key' => 'devkey1234567890',
            'services.livekit.api_secret' => 'supersecretkey_must_be_32_characters_long_min',
            'services.livekit.token_ttl' => 3600,
        ]);
    }

    private function assignedTrip(DriverProfile $driver, EmergencyStatus $status): EmergencyRequest
    {
        $driver->update(['availability_status' => DriverAvailability::Busy->value]);

        return EmergencyRequest::factory()->create([
            'status' => $status->value,
            'driver_profile_id' => $driver->id,
            'ambulance_id' => $driver->ambulance->id,
            'accepted_at' => now(),
        ]);
    }

    public function test_allows_an_authenticated_user_to_initiate_a_call_to_another_user(): void
    {
        Event::fake([CallIncoming::class]);

        $caller = User::factory()->create(['full_name' => 'Alice Caller']);
        $callee = User::factory()->create(['full_name' => 'Bob Callee']);

        Sanctum::actingAs($caller);

        $response = $this->postJson('/api/v1/calls', [
            'callee_id' => $callee->id,
            'call_type' => 'voice',
            'metadata' => ['device' => 'flutter'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'ringing')
            ->assertJsonPath('data.call_type', 'voice')
            ->assertJsonPath('data.caller.id', (string) $caller->id)
            ->assertJsonPath('data.callee.id', (string) $callee->id)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'room_name',
                    'call_type',
                    'status',
                    'caller' => ['id', 'name', 'phone'],
                    'callee' => ['id', 'name', 'phone'],
                    'started_at',
                ],
            ]);

        $callId = $response->json('data.id');
        $call = Call::findOrFail($callId);

        $this->assertEquals(CallStatus::Ringing, $call->status);
        $this->assertNotEmpty($call->room_name);

        Event::assertDispatched(CallIncoming::class, function ($event) use ($callee, $callId) {
            $channels = collect($event->broadcastOn())->map(fn ($c) => (string) $c)->all();
            $payload = $event->broadcastWith();

            return in_array('private-users.'.$callee->id.'.calls', $channels, true)
                && $payload['call_id'] === $callId
                && ! isset($payload['token'])
                && ! isset($payload['api_secret']);
        });
    }

    public function test_prevents_a_user_from_calling_themselves(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/calls', [
            'callee_id' => $user->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['callee_id']);
    }

    public function test_prevents_initiating_a_call_when_callee_has_an_ongoing_call(): void
    {
        $caller = User::factory()->create();
        $callee = User::factory()->create();
        $other = User::factory()->create();

        Call::factory()->create([
            'caller_id' => $other->id,
            'callee_id' => $callee->id,
            'status' => CallStatus::Accepted,
        ]);

        Sanctum::actingAs($caller);

        $response = $this->postJson('/api/v1/calls', [
            'callee_id' => $callee->id,
        ]);

        $response->assertStatus(409)
            ->assertSee('User tujuan sedang dalam panggilan lain.');
    }

    public function test_prevents_initiating_a_call_when_caller_already_has_an_ongoing_call(): void
    {
        $caller = User::factory()->create();
        $callee = User::factory()->create();
        $other = User::factory()->create();

        Call::factory()->create([
            'caller_id' => $caller->id,
            'callee_id' => $other->id,
            'status' => CallStatus::Ringing,
        ]);

        Sanctum::actingAs($caller);

        $response = $this->postJson('/api/v1/calls', [
            'callee_id' => $callee->id,
        ]);

        $response->assertStatus(409)
            ->assertSee('Anda masih memiliki panggilan yang sedang berlangsung.');
    }

    public function test_allows_callee_to_accept_a_ringing_call(): void
    {
        Event::fake([CallAccepted::class]);

        $caller = User::factory()->create();
        $callee = User::factory()->create();
        $call = Call::factory()->create([
            'caller_id' => $caller->id,
            'callee_id' => $callee->id,
            'status' => CallStatus::Ringing,
        ]);

        Sanctum::actingAs($callee);

        $response = $this->postJson("/api/v1/calls/{$call->id}/accept");

        $response->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.id', (string) $call->id);

        $call->refresh();
        $this->assertEquals(CallStatus::Accepted, $call->status);
        $this->assertNotNull($call->accepted_at);

        Event::assertDispatched(CallAccepted::class, function ($event) use ($caller) {
            $channels = collect($event->broadcastOn())->map(fn ($c) => (string) $c)->all();
            $payload = $event->broadcastWith();

            return in_array('private-users.'.$caller->id.'.calls', $channels, true)
                && $payload['status'] === 'accepted'
                && ! isset($payload['token']);
        });
    }

    public function test_forbids_caller_or_strangers_from_accepting_a_call(): void
    {
        $caller = User::factory()->create();
        $callee = User::factory()->create();
        $stranger = User::factory()->create();
        $call = Call::factory()->create([
            'caller_id' => $caller->id,
            'callee_id' => $callee->id,
            'status' => CallStatus::Ringing,
        ]);

        Sanctum::actingAs($caller);
        $this->postJson("/api/v1/calls/{$call->id}/accept")->assertForbidden();

        Sanctum::actingAs($stranger);
        $this->postJson("/api/v1/calls/{$call->id}/accept")->assertForbidden();
    }

    public function test_cannot_accept_a_call_if_status_is_not_ringing(): void
    {
        $caller = User::factory()->create();
        $callee = User::factory()->create();
        $call = Call::factory()->active()->create([
            'caller_id' => $caller->id,
            'callee_id' => $callee->id,
        ]);

        Sanctum::actingAs($callee);

        $this->postJson("/api/v1/calls/{$call->id}/accept")
            ->assertStatus(409);
    }

    public function test_allows_callee_to_reject_a_ringing_call(): void
    {
        Event::fake([CallRejected::class]);

        $caller = User::factory()->create();
        $callee = User::factory()->create();
        $call = Call::factory()->create([
            'caller_id' => $caller->id,
            'callee_id' => $callee->id,
            'status' => CallStatus::Ringing,
        ]);

        Sanctum::actingAs($callee);

        $response = $this->postJson("/api/v1/calls/{$call->id}/reject", [
            'reason' => 'busy',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $call->refresh();
        $this->assertEquals(CallStatus::Rejected, $call->status);
        $this->assertNotNull($call->ended_at);
        $this->assertEquals((string) $callee->id, (string) $call->ended_by_id);

        Event::assertDispatched(CallRejected::class, function ($event) use ($caller) {
            $channels = collect($event->broadcastOn())->map(fn ($c) => (string) $c)->all();
            $payload = $event->broadcastWith();

            return in_array('private-users.'.$caller->id.'.calls', $channels, true)
                && ! isset($payload['token']);
        });
    }

    public function test_cannot_reject_a_call_if_status_is_not_ringing(): void
    {
        $caller = User::factory()->create();
        $callee = User::factory()->create();
        $call = Call::factory()->ended()->create([
            'caller_id' => $caller->id,
            'callee_id' => $callee->id,
        ]);

        Sanctum::actingAs($callee);

        $this->postJson("/api/v1/calls/{$call->id}/reject")
            ->assertStatus(409);
    }

    public function test_allows_either_caller_or_callee_to_end_an_active_call(): void
    {
        Event::fake([CallEnded::class]);

        $caller = User::factory()->create();
        $callee = User::factory()->create();
        $call = Call::factory()->active()->create([
            'caller_id' => $caller->id,
            'callee_id' => $callee->id,
            'accepted_at' => now()->subSeconds(45),
        ]);

        Sanctum::actingAs($caller);

        $response = $this->postJson("/api/v1/calls/{$call->id}/end");

        $response->assertOk()
            ->assertJsonPath('data.status', 'ended');

        $call->refresh();
        $this->assertEquals(CallStatus::Ended, $call->status);
        $this->assertNotNull($call->ended_at);
        $this->assertEquals((string) $caller->id, (string) $call->ended_by_id);
        $this->assertGreaterThanOrEqual(45, $call->duration_seconds);

        Event::assertDispatched(CallEnded::class, function ($event) use ($caller, $callee) {
            $channels = collect($event->broadcastOn())->map(fn ($c) => (string) $c)->all();
            $payload = $event->broadcastWith();

            return in_array('private-users.'.$caller->id.'.calls', $channels, true)
                && in_array('private-users.'.$callee->id.'.calls', $channels, true)
                && $payload['status'] === 'ended';
        });
    }

    public function test_cannot_end_a_call_that_has_already_ended(): void
    {
        $caller = User::factory()->create();
        $callee = User::factory()->create();
        $call = Call::factory()->ended()->create([
            'caller_id' => $caller->id,
            'callee_id' => $callee->id,
        ]);

        Sanctum::actingAs($callee);

        $this->postJson("/api/v1/calls/{$call->id}/end")
            ->assertStatus(409);
    }

    public function test_forbids_stranger_from_ending_a_call(): void
    {
        $caller = User::factory()->create();
        $callee = User::factory()->create();
        $stranger = User::factory()->create();
        $call = Call::factory()->active()->create([
            'caller_id' => $caller->id,
            'callee_id' => $callee->id,
        ]);

        Sanctum::actingAs($stranger);

        $this->postJson("/api/v1/calls/{$call->id}/end")->assertForbidden();
    }

    public function test_allows_participants_to_fetch_a_valid_signed_livekit_room_token(): void
    {
        $caller = User::factory()->create(['full_name' => 'Alice Caller']);
        $callee = User::factory()->create(['full_name' => 'Bob Callee']);
        $call = Call::factory()->active()->create([
            'caller_id' => $caller->id,
            'callee_id' => $callee->id,
        ]);

        Sanctum::actingAs($caller);

        $response = $this->getJson("/api/v1/calls/{$call->id}/token");

        $response->assertOk()
            ->assertJsonStructure([
                'token',
                'url',
                'room_name',
                'identity',
                'expires_in',
            ])
            ->assertJsonPath('url', 'wss://example.livekit.cloud')
            ->assertJsonPath('room_name', (string) $call->room_name)
            ->assertJsonPath('identity', (string) $caller->id);

        // Decode token and verify claims
        $secret = config('services.livekit.api_secret');
        $jwt = $response->json('token');
        $decoded = JWT::decode($jwt, new Key($secret, 'HS256'));

        $this->assertSame(config('services.livekit.api_key'), $decoded->iss);
        $this->assertSame((string) $caller->id, $decoded->sub);
        $this->assertTrue($decoded->video->roomJoin);
        $this->assertSame($call->room_name, $decoded->video->room);
        $this->assertTrue($decoded->video->canPublish);
        $this->assertTrue($decoded->video->canSubscribe);
        $this->assertTrue($decoded->video->canPublishData);
    }

    public function test_forbids_non_participants_from_fetching_a_livekit_token(): void
    {
        $caller = User::factory()->create();
        $callee = User::factory()->create();
        $stranger = User::factory()->create();
        $call = Call::factory()->active()->create([
            'caller_id' => $caller->id,
            'callee_id' => $callee->id,
        ]);

        Sanctum::actingAs($stranger);

        $this->getJson("/api/v1/calls/{$call->id}/token")->assertForbidden();
    }

    public function test_prevents_fetching_token_if_call_has_ended(): void
    {
        $caller = User::factory()->create();
        $callee = User::factory()->create();
        $call = Call::factory()->ended()->create([
            'caller_id' => $caller->id,
            'callee_id' => $callee->id,
        ]);

        Sanctum::actingAs($caller);

        $this->getJson("/api/v1/calls/{$call->id}/token")
            ->assertStatus(409)
            ->assertSee('Panggilan sudah tidak aktif.');
    }

    public function test_allows_participant_to_view_call_details_and_forbids_strangers(): void
    {
        $caller = User::factory()->create();
        $callee = User::factory()->create();
        $stranger = User::factory()->create();
        $call = Call::factory()->create([
            'caller_id' => $caller->id,
            'callee_id' => $callee->id,
            'status' => CallStatus::Ringing,
        ]);

        Sanctum::actingAs($caller);
        $this->getJson("/api/v1/calls/{$call->id}")
            ->assertOk()
            ->assertJsonPath('data.id', (string) $call->id);

        Sanctum::actingAs($stranger);
        $this->getJson("/api/v1/calls/{$call->id}")
            ->assertForbidden();
    }

    public function test_handles_livekit_webhook_with_signature_verification(): void
    {
        $secret = config('services.livekit.api_secret');
        $apiKey = config('services.livekit.api_key');

        $payload = [
            'event' => 'room_finished',
            'room' => [
                'name' => 'test-room-uuid',
                'duration' => 60,
            ],
        ];
        $json = json_encode($payload);

        // Create call with room_name test-room-uuid
        $caller = User::factory()->create();
        $callee = User::factory()->create();
        $call = Call::factory()->active()->create([
            'caller_id' => $caller->id,
            'callee_id' => $callee->id,
            'room_name' => 'test-room-uuid',
        ]);

        // Test invalid signature
        $this->postJson('/api/v1/livekit/webhook', $payload, [
            'Authorization' => 'Bearer invalid_token',
        ])->assertStatus(401);

        // Create valid webhook token
        $token = JWT::encode([
            'iss' => $apiKey,
            'sha256' => hash('sha256', $json),
            'exp' => time() + 60,
        ], $secret, 'HS256');

        $response = $this->call('POST', '/api/v1/livekit/webhook', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'CONTENT_TYPE' => 'application/json',
        ], $json);

        $response->assertOk()
            ->assertJson(['status' => 'handled', 'event' => 'room_finished']);

        $this->assertEquals(CallStatus::Ended, $call->fresh()->status);
    }

    public function test_issues_a_livekit_token_for_a_trip_call_reporter(): void
    {
        $driver = DriverProfile::factory()->withAmbulance()->create();
        $trip = $this->assignedTrip($driver, EmergencyStatus::EnRouteToPickup);
        $call = TripCall::factory()->for($trip)->create(['caller_id' => $trip->user_id]);

        Sanctum::actingAs($trip->user);

        $response = $this->getJson("/api/v1/calls/{$call->id}/token");

        $response->assertOk()
            ->assertJsonPath('url', 'wss://example.livekit.cloud')
            ->assertJsonPath('room_name', (string) $call->room_name)
            ->assertJsonPath('identity', (string) $trip->user_id)
            ->assertJsonPath('expires_in', 3600);

        $decoded = JWT::decode($response->json('token'), new Key(config('services.livekit.api_secret'), 'HS256'));

        $this->assertSame((string) $trip->user_id, $decoded->sub);
        $this->assertSame($call->room_name, $decoded->video->room);
        $this->assertTrue($decoded->video->roomJoin);
    }

    public function test_issues_a_livekit_token_for_the_assigned_driver_on_a_trip_call(): void
    {
        $driver = DriverProfile::factory()->withAmbulance()->create();
        $trip = $this->assignedTrip($driver, EmergencyStatus::EnRouteToPickup);
        $call = TripCall::factory()->for($trip)->create(['caller_id' => $trip->user_id]);

        Sanctum::actingAs($driver->user);

        $this->getJson("/api/v1/calls/{$call->id}/token")
            ->assertOk()
            ->assertJsonPath('room_name', (string) $call->room_name)
            ->assertJsonPath('identity', (string) $driver->user_id);
    }

    public function test_issues_a_livekit_token_for_the_patient_when_the_driver_placed_the_call(): void
    {
        $driver = DriverProfile::factory()->withAmbulance()->create();
        $trip = $this->assignedTrip($driver, EmergencyStatus::EnRouteToPickup);
        $call = TripCall::factory()->for($trip)->create(['caller_id' => $driver->user_id]);

        Sanctum::actingAs($trip->user);

        $this->getJson("/api/v1/calls/{$call->id}/token")
            ->assertOk()
            ->assertJsonPath('room_name', (string) $call->room_name)
            ->assertJsonPath('identity', (string) $trip->user_id);
    }

    public function test_forbids_unrelated_users_from_a_trip_call_token(): void
    {
        $driver = DriverProfile::factory()->withAmbulance()->create();
        $trip = $this->assignedTrip($driver, EmergencyStatus::EnRouteToPickup);
        $call = TripCall::factory()->for($trip)->create(['caller_id' => $trip->user_id]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/calls/{$call->id}/token")->assertForbidden();
    }

    public function test_rejects_a_token_for_an_inactive_trip_call(): void
    {
        $driver = DriverProfile::factory()->withAmbulance()->create();
        $trip = $this->assignedTrip($driver, EmergencyStatus::EnRouteToPickup);
        $call = TripCall::factory()->for($trip)->create([
            'caller_id' => $trip->user_id,
            'status' => LegacyCallStatus::Declined->value,
        ]);

        Sanctum::actingAs($trip->user);

        $this->getJson("/api/v1/calls/{$call->id}/token")->assertConflict();
    }

    public function test_returns_404_for_a_token_on_an_unknown_call(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/calls/'.fake()->uuid().'/token')->assertNotFound();
    }
}
