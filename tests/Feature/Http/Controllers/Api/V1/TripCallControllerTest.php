<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Enums\DriverAvailability;
use App\Enums\EmergencyStatus;
use App\Models\DriverProfile;
use App\Models\EmergencyRequest;
use App\Models\Role;
use App\Models\TripCall;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TripCallControllerTest extends TestCase
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

    public function test_rings_the_driver_of_the_reporter_active_order(): void
    {
        $driver = DriverProfile::factory()->withAmbulance()->create();
        $trip = $this->assignedTrip($driver, EmergencyStatus::EnRouteToPickup);
        $reporter = $trip->user;
        $reporter->roles()->attach(Role::firstOrCreate(['name' => 'user']));
        Sanctum::actingAs($reporter);

        $response = $this->postJson("/api/v1/emergency-requests/{$trip->id}/calls");

        $response->assertCreated()
            ->assertJsonPath('data.status', 'ringing')
            ->assertJsonPath('data.caller.name', $reporter->full_name)
            ->assertJsonPath('data.request.code', $trip->request_code)
            ->assertJsonPath('data.ring_seconds_remaining', 30);

        $this->assertDatabaseHas('trip_calls', [
            'emergency_request_id' => $trip->id,
            'caller_id' => $reporter->id,
        ]);
    }

    public function test_points_the_caller_at_the_call_the_other_side_is_already_ringing(): void
    {
        $driver = DriverProfile::factory()->withAmbulance()->create();
        $trip = $this->assignedTrip($driver, EmergencyStatus::EnRouteToPickup);
        $ringing = TripCall::factory()->for($trip)->create(['caller_id' => $driver->user_id]);
        $trip->user->roles()->attach(Role::firstOrCreate(['name' => 'user']));
        Sanctum::actingAs($trip->user);

        $response = $this->postJson("/api/v1/emergency-requests/{$trip->id}/calls");

        $response->assertStatus(409)
            ->assertJsonPath('incoming_call_id', $ringing->id);

        $this->assertEquals(1, $trip->calls()->count());
    }

    public function test_returns_the_same_call_for_a_repeated_request_while_it_rings(): void
    {
        $driver = DriverProfile::factory()->withAmbulance()->create();
        $trip = $this->assignedTrip($driver, EmergencyStatus::EnRouteToPickup);
        $mine = TripCall::factory()->for($trip)->create(['caller_id' => $trip->user_id]);
        $trip->user->roles()->attach(Role::firstOrCreate(['name' => 'user']));
        Sanctum::actingAs($trip->user);

        $response = $this->postJson("/api/v1/emergency-requests/{$trip->id}/calls");

        $response->assertOk()
            ->assertJsonPath('data.id', $mine->id);

        $this->assertEquals(1, $trip->calls()->count());
    }

    public function test_replaces_an_answered_call_of_the_caller_that_was_never_hung_up(): void
    {
        $driver = DriverProfile::factory()->withAmbulance()->create();
        $trip = $this->assignedTrip($driver, EmergencyStatus::EnRouteToPickup);
        $stale = TripCall::factory()->for($trip)->create([
            'caller_id' => $trip->user_id,
            'status' => 'answered',
            'answered_at' => now()->subMinutes(5),
        ]);
        $trip->user->roles()->attach(Role::firstOrCreate(['name' => 'user']));
        Sanctum::actingAs($trip->user);

        $response = $this->postJson("/api/v1/emergency-requests/{$trip->id}/calls");

        $response->assertCreated();
        $this->assertEquals('ended', $stale->fresh()->status);
        $this->assertEquals(1, $trip->calls()->live()->count());
    }

    public function test_allows_a_new_call_once_the_last_one_was_missed(): void
    {
        $driver = DriverProfile::factory()->withAmbulance()->create();
        $trip = $this->assignedTrip($driver, EmergencyStatus::EnRouteToPickup);
        $missed = TripCall::factory()->for($trip)->create(['caller_id' => $trip->user_id]);
        $trip->user->roles()->attach(Role::firstOrCreate(['name' => 'user']));
        Sanctum::actingAs($trip->user);

        $this->travel(31)->seconds();

        $response = $this->postJson("/api/v1/emergency-requests/{$trip->id}/calls");

        $response->assertCreated();
        $this->assertEquals('missed', $missed->fresh()->status);
    }

    public function test_refuses_to_call_before_a_driver_is_assigned(): void
    {
        $user = User::factory()->withRole('user')->create();
        $emergency = EmergencyRequest::factory()->create([
            'user_id' => $user->id,
            'status' => EmergencyStatus::SearchingDriver->value,
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/emergency-requests/{$emergency->id}/calls");

        $response->assertStatus(409);
    }

    public function test_hides_other_users_orders(): void
    {
        $driver = DriverProfile::factory()->withAmbulance()->create();
        $trip = $this->assignedTrip($driver, EmergencyStatus::EnRouteToPickup);
        Sanctum::actingAs(User::factory()->withRole('user')->create());

        $response = $this->postJson("/api/v1/emergency-requests/{$trip->id}/calls");

        $response->assertNotFound();
    }
}

