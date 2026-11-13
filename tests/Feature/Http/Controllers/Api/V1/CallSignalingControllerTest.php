<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Enums\CallStatus;
use App\Enums\DriverAvailability;
use App\Enums\EmergencyStatus;
use App\Events\CallSignalEvent;
use App\Events\CallStatusUpdated;
use App\Models\DriverProfile;
use App\Models\EmergencyRequest;
use App\Models\TripCall;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CallSignalingControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
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

    public function test_allows_caller_to_send_a_webrtc_signal(): void
    {
        Event::fake([CallSignalEvent::class]);

        $driver = DriverProfile::factory()->withAmbulance()->create();
        $trip = $this->assignedTrip($driver, EmergencyStatus::EnRouteToPickup);
        $call = TripCall::factory()->for($trip)->create([
            'caller_id' => $trip->user_id,
            'status' => CallStatus::Ringing->value,
        ]);

        Sanctum::actingAs($trip->user);

        $response = $this->postJson("/api/v1/calls/{$call->id}/signal", [
            'type' => 'offer',
            'data' => ['sdp' => 'v=0...', 'type' => 'offer'],
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        Event::assertDispatched(CallSignalEvent::class, function ($event) use ($call, $trip) {
            return $event->callId === (string) $call->id
                && $event->senderId === (string) $trip->user->id
                && $event->type === 'offer';
        });
    }

    public function test_allows_driver_to_send_a_webrtc_signal(): void
    {
        Event::fake([CallSignalEvent::class]);

        $driver = DriverProfile::factory()->withAmbulance()->create();
        $trip = $this->assignedTrip($driver, EmergencyStatus::EnRouteToPickup);
        $call = TripCall::factory()->for($trip)->create([
            'caller_id' => $trip->user_id,
            'status' => CallStatus::Answered->value,
        ]);

        Sanctum::actingAs($driver->user);

        $response = $this->postJson("/api/v1/calls/{$call->id}/signal", [
            'type' => 'answer',
            'data' => ['sdp' => 'v=0...', 'type' => 'answer'],
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        Event::assertDispatched(CallSignalEvent::class, function ($event) use ($call, $driver) {
            return $event->callId === (string) $call->id
                && $event->senderId === (string) $driver->user->id
                && $event->type === 'answer';
        });
    }

    public function test_forbids_unrelated_users_from_sending_signals(): void
    {
        $driver = DriverProfile::factory()->withAmbulance()->create();
        $trip = $this->assignedTrip($driver, EmergencyStatus::EnRouteToPickup);
        $call = TripCall::factory()->for($trip)->create([
            'caller_id' => $trip->user_id,
            'status' => CallStatus::Ringing->value,
        ]);

        $stranger = User::factory()->create();
        Sanctum::actingAs($stranger);

        $this->postJson("/api/v1/calls/{$call->id}/signal", [
            'type' => 'candidate',
            'data' => ['candidate' => 'candidate:...'],
        ])->assertForbidden();
    }

    public function test_allows_caller_to_end_an_active_call(): void
    {
        Event::fake([CallStatusUpdated::class]);

        $driver = DriverProfile::factory()->withAmbulance()->create();
        $trip = $this->assignedTrip($driver, EmergencyStatus::EnRouteToPickup);
        $call = TripCall::factory()->for($trip)->create([
            'caller_id' => $trip->user_id,
            'status' => CallStatus::Answered->value,
        ]);

        Sanctum::actingAs($trip->user);

        $this->postJson("/api/v1/calls/{$call->id}/end")->assertOk()->assertJson(['success' => true]);

        $this->assertEquals('ended', $call->fresh()->status);
        $this->assertNotNull($call->fresh()->ended_at);

        Event::assertDispatched(CallStatusUpdated::class, function ($event) use ($call) {
            return $event->call->id === $call->id && $event->status === 'ended';
        });
    }

    public function test_shows_the_call_state_to_the_reporter_while_it_rings(): void
    {
        $driver = DriverProfile::factory()->withAmbulance()->create();
        $trip = $this->assignedTrip($driver, EmergencyStatus::EnRouteToPickup);
        $call = TripCall::factory()->for($trip)->create([
            'caller_id' => $trip->user_id,
            'status' => CallStatus::Answered->value,
            'answered_at' => now(),
        ]);

        Sanctum::actingAs($trip->user);

        $this->getJson("/api/v1/calls/{$call->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $call->id)
            ->assertJsonPath('data.status', 'answered');
    }

    public function test_marks_an_unanswered_call_missed_when_it_is_polled_after_ringing_out(): void
    {
        $driver = DriverProfile::factory()->withAmbulance()->create();
        $trip = $this->assignedTrip($driver, EmergencyStatus::EnRouteToPickup);
        $call = TripCall::factory()->for($trip)->create([
            'caller_id' => $trip->user_id,
            'status' => CallStatus::Ringing->value,
            'rang_at' => now()->subSeconds(TripCall::RING_SECONDS + 1),
        ]);

        Sanctum::actingAs($trip->user);

        $this->getJson("/api/v1/calls/{$call->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'missed');
    }

    public function test_hides_the_call_state_from_unrelated_users(): void
    {
        $driver = DriverProfile::factory()->withAmbulance()->create();
        $trip = $this->assignedTrip($driver, EmergencyStatus::EnRouteToPickup);
        $call = TripCall::factory()->for($trip)->create(['caller_id' => $trip->user_id]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/calls/{$call->id}")->assertForbidden();
    }

    public function test_lets_the_callee_say_it_is_ready_for_the_offer(): void
    {
        Event::fake([CallSignalEvent::class]);

        $driver = DriverProfile::factory()->withAmbulance()->create();
        $trip = $this->assignedTrip($driver, EmergencyStatus::EnRouteToPickup);
        $call = TripCall::factory()->for($trip)->create([
            'caller_id' => $trip->user_id,
            'status' => CallStatus::Answered->value,
        ]);

        Sanctum::actingAs($driver->user);

        $this->postJson("/api/v1/calls/{$call->id}/signal", ['type' => 'ready', 'data' => ['ready' => true]])
            ->assertOk();

        Event::assertDispatched(CallSignalEvent::class, fn ($event) => $event->type === 'ready');
    }

    public function test_lets_the_reporter_signal_on_a_call_the_driver_placed(): void
    {
        Event::fake([CallSignalEvent::class]);

        $driver = DriverProfile::factory()->withAmbulance()->create();
        $trip = $this->assignedTrip($driver, EmergencyStatus::EnRouteToPickup);
        $call = TripCall::factory()->for($trip)->create([
            'caller_id' => $driver->user_id,
            'status' => CallStatus::Answered->value,
        ]);

        Sanctum::actingAs($trip->user);

        $this->postJson("/api/v1/calls/{$call->id}/signal", ['type' => 'answer', 'data' => ['sdp' => 'v=0...']])
            ->assertOk();
    }

    public function test_still_answers_the_call_when_broadcasting_fails(): void
    {
        Event::listen(CallStatusUpdated::class, fn () => throw new \RuntimeException('Reverb is down'));

        $driver = DriverProfile::factory()->withAmbulance()->create();
        $trip = $this->assignedTrip($driver, EmergencyStatus::EnRouteToPickup);
        $call = TripCall::factory()->for($trip)->create([
            'caller_id' => $trip->user_id,
            'status' => CallStatus::Ringing->value,
            'rang_at' => now(),
        ]);

        Sanctum::actingAs($driver->user);

        $this->postJson("/api/v1/driver/calls/{$call->id}/answer")
            ->assertOk()
            ->assertJsonPath('data.status', 'answered');

        $this->assertEquals('answered', $call->fresh()->status);
    }

    public function test_keeps_the_line_endings_of_a_webrtc_session_description(): void
    {
        Event::fake([CallSignalEvent::class]);

        $driver = DriverProfile::factory()->withAmbulance()->create();
        $trip = $this->assignedTrip($driver, EmergencyStatus::EnRouteToPickup);
        $call = TripCall::factory()->for($trip)->create([
            'caller_id' => $trip->user_id,
            'status' => CallStatus::Answered->value,
        ]);
        $sdp = "v=0\r\no=- 1 2 IN IP4 127.0.0.1\r\na=end-of-candidates\r\n";

        Sanctum::actingAs($trip->user);

        $this->postJson("/api/v1/calls/{$call->id}/signal", ['type' => 'offer', 'data' => ['sdp' => $sdp, 'type' => 'offer']])
            ->assertOk();

        Event::assertDispatched(CallSignalEvent::class, fn ($event) => $event->data['sdp'] === $sdp);
    }

    public function test_rings_the_reporter_when_the_driver_calls(): void
    {
        $driver = DriverProfile::factory()->withAmbulance()->create();
        $trip = $this->assignedTrip($driver, EmergencyStatus::EnRouteToPickup);
        $call = TripCall::factory()->for($trip)->create([
            'caller_id' => $driver->user_id,
            'status' => CallStatus::Ringing->value,
            'rang_at' => now(),
        ]);

        Sanctum::actingAs($trip->user);

        $this->getJson('/api/v1/calls/incoming')
            ->assertOk()
            ->assertJsonPath('data.id', $call->id)
            ->assertJsonPath('data.caller.name', $driver->user->full_name);
    }

    public function test_does_not_ring_the_caller_for_their_own_call(): void
    {
        $driver = DriverProfile::factory()->withAmbulance()->create();
        $trip = $this->assignedTrip($driver, EmergencyStatus::EnRouteToPickup);
        TripCall::factory()->for($trip)->create([
            'caller_id' => $driver->user_id,
            'status' => CallStatus::Ringing->value,
            'rang_at' => now(),
        ]);

        Sanctum::actingAs($driver->user);

        $this->getJson('/api/v1/calls/incoming')->assertOk()->assertJsonPath('data', null);
        $this->getJson('/api/v1/driver/calls/current')->assertOk()->assertJsonPath('data', null);
    }

    public function test_lets_the_reporter_answer_the_drivers_call(): void
    {
        Event::fake([CallStatusUpdated::class]);

        $driver = DriverProfile::factory()->withAmbulance()->create();
        $trip = $this->assignedTrip($driver, EmergencyStatus::EnRouteToPickup);
        $call = TripCall::factory()->for($trip)->create([
            'caller_id' => $driver->user_id,
            'status' => CallStatus::Ringing->value,
            'rang_at' => now(),
        ]);

        Sanctum::actingAs($trip->user);

        $this->postJson("/api/v1/calls/{$call->id}/answer")
            ->assertOk()
            ->assertJsonPath('data.status', 'answered');

        Event::assertDispatched(CallStatusUpdated::class, fn ($event) => $event->status === 'answered');
    }

    public function test_lets_the_reporter_decline_the_drivers_call(): void
    {
        $driver = DriverProfile::factory()->withAmbulance()->create();
        $trip = $this->assignedTrip($driver, EmergencyStatus::EnRouteToPickup);
        $call = TripCall::factory()->for($trip)->create([
            'caller_id' => $driver->user_id,
            'status' => CallStatus::Ringing->value,
            'rang_at' => now(),
        ]);

        Sanctum::actingAs($trip->user);

        $this->postJson("/api/v1/calls/{$call->id}/decline")->assertOk();

        $this->assertEquals('declined', $call->fresh()->status);
    }

    public function test_does_not_let_the_caller_answer_their_own_call(): void
    {
        $driver = DriverProfile::factory()->withAmbulance()->create();
        $trip = $this->assignedTrip($driver, EmergencyStatus::EnRouteToPickup);
        $call = TripCall::factory()->for($trip)->create([
            'caller_id' => $driver->user_id,
            'status' => CallStatus::Ringing->value,
            'rang_at' => now(),
        ]);

        Sanctum::actingAs($driver->user);

        $this->postJson("/api/v1/calls/{$call->id}/answer")->assertForbidden();
    }

    public function test_returns_ice_servers_for_call_participants(): void
    {
        $driver = DriverProfile::factory()->withAmbulance()->create();
        $trip = $this->assignedTrip($driver, EmergencyStatus::EnRouteToPickup);
        $call = TripCall::factory()->for($trip)->create([
            'caller_id' => $trip->user_id,
            'status' => CallStatus::Answered->value,
        ]);

        Sanctum::actingAs($trip->user);

        $this->getJson("/api/v1/calls/{$call->id}/ice-servers")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'ice_servers' => [
                        '*' => ['urls'],
                    ],
                ],
            ]);
    }
}

