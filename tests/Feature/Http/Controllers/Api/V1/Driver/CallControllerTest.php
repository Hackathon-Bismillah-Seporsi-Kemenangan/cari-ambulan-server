<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Driver;

use App\Enums\DriverAvailability;
use App\Enums\EmergencyStatus;
use App\Models\DriverProfile;
use App\Models\EmergencyRequest;
use App\Models\TripCall;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CallControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function actingAsDriver(DriverProfile $driver): DriverProfile
    {
        Sanctum::actingAs($driver->user);

        return $driver;
    }

    private function assignedTrip(DriverProfile $driver, EmergencyStatus $status = EmergencyStatus::EnRouteToPickup): EmergencyRequest
    {
        $driver->update(['availability_status' => DriverAvailability::Busy->value]);

        return EmergencyRequest::factory()->create([
            'status' => $status->value,
            'driver_profile_id' => $driver->id,
            'ambulance_id' => $driver->ambulance->id,
            'accepted_at' => now(),
        ]);
    }

    private function ringingCallFor(DriverProfile $driver, EmergencyStatus $status = EmergencyStatus::EnRouteToPickup): TripCall
    {
        $trip = $this->assignedTrip($driver, $status);

        return TripCall::factory()->for($trip)->create(['caller_id' => $trip->user_id]);
    }

    public function test_returns_the_call_ringing_on_the_active_trip(): void
    {
        $driver = $this->actingAsDriver(DriverProfile::factory()->withAmbulance()->create());
        $call = $this->ringingCallFor($driver);

        $response = $this->getJson('/api/v1/driver/calls/current');

        $response->assertOk()
            ->assertJsonPath('data.id', $call->id)
            ->assertJsonPath('data.status', 'ringing')
            ->assertJsonPath('data.caller.name', $call->caller->full_name)
            ->assertJsonPath('data.request.code', $call->emergencyRequest->request_code);
    }

    public function test_returns_null_without_a_trip_or_call(): void
    {
        $this->actingAsDriver(DriverProfile::factory()->withAmbulance()->create());

        $response = $this->getJson('/api/v1/driver/calls/current');

        $response->assertOk()
            ->assertExactJson(['data' => null]);
    }

    public function test_marks_a_call_nobody_answered_in_time_as_missed(): void
    {
        $driver = $this->actingAsDriver(DriverProfile::factory()->withAmbulance()->create());
        $call = $this->ringingCallFor($driver);
        $this->travel(31)->seconds();

        $response = $this->getJson('/api/v1/driver/calls/current');

        $response->assertExactJson(['data' => null]);
        $this->assertEquals('missed', $call->fresh()->status);
    }

    public function test_answers_a_ringing_call(): void
    {
        $this->freezeTime();
        $driver = $this->actingAsDriver(DriverProfile::factory()->withAmbulance()->create());
        $call = $this->ringingCallFor($driver);

        $response = $this->postJson("/api/v1/driver/calls/{$call->id}/answer");

        $response->assertOk()
            ->assertJsonPath('data.status', 'answered');

        $this->assertNotNull($call->fresh()->answered_at);

        $this->travel(12)->seconds();
        $responseCurrent = $this->getJson('/api/v1/driver/calls/current');

        $responseCurrent->assertJsonPath('data.status', 'answered')
            ->assertJsonPath('data.talk_seconds', 12);
    }

    public function test_declines_a_ringing_call(): void
    {
        $driver = $this->actingAsDriver(DriverProfile::factory()->withAmbulance()->create());
        $call = $this->ringingCallFor($driver);

        $response = $this->postJson("/api/v1/driver/calls/{$call->id}/decline");

        $response->assertNoContent();
        $this->assertEquals('declined', $call->fresh()->status);
    }

    public function test_ends_an_answered_call(): void
    {
        $driver = $this->actingAsDriver(DriverProfile::factory()->withAmbulance()->create());
        $call = $this->ringingCallFor($driver);
        $this->postJson("/api/v1/driver/calls/{$call->id}/answer")->assertOk();

        $response = $this->postJson("/api/v1/driver/calls/{$call->id}/end");

        $response->assertNoContent();
        $this->assertEquals('ended', $call->fresh()->status);
        $this->assertNotNull($call->fresh()->ended_at);
    }

    public function test_hides_calls_on_other_drivers_trips(): void
    {
        $driver = $this->actingAsDriver(DriverProfile::factory()->withAmbulance()->create());
        $otherDriver = DriverProfile::factory()->withAmbulance()->create();
        $otherCall = $this->ringingCallFor($otherDriver);

        $this->postJson("/api/v1/driver/calls/{$otherCall->id}/answer")->assertNotFound();
        $this->postJson("/api/v1/driver/calls/{$otherCall->id}/decline")->assertNotFound();
        $this->postJson("/api/v1/driver/calls/{$otherCall->id}/end")->assertNotFound();
    }
}

