<?php

namespace Tests\Feature\Console;

use App\Enums\CallStatus;
use App\Enums\DriverAvailability;
use App\Enums\EmergencyStatus;
use App\Models\DriverProfile;
use App\Models\EmergencyRequest;
use App\Models\TripCall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CloseAbandonedEmergencyRequestsTest extends TestCase
{
    use RefreshDatabase;

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

    private function idleFor(EmergencyRequest $emergency, int $minutes): void
    {
        $emergency->forceFill(['updated_at' => now()->subMinutes($minutes)])->saveQuietly();
    }

    public function test_closes_an_order_the_driver_left_at_the_pickup(): void
    {
        $driver = DriverProfile::factory()->withAmbulance()->create();
        $trip = $this->assignedTrip($driver, EmergencyStatus::ArriveAtPickup);
        $call = TripCall::factory()->for($trip)->create([
            'caller_id' => $trip->user_id,
            'status' => CallStatus::Answered->value,
        ]);
        $this->idleFor($trip, 121);

        $this->assertSame(1, EmergencyRequest::closeAbandoned());

        $trip->refresh();
        $this->assertSame(EmergencyStatus::Cancelled->value, $trip->status);
        $this->assertNotNull($trip->cancelled_at);
        $this->assertStringContainsString('Ditutup otomatis', $trip->cancel_reason);
        $this->assertSame(CallStatus::Ended->value, $call->refresh()->status);
        $this->assertSame(DriverAvailability::Offline, $driver->refresh()->availability_status);
        $this->assertFalse($trip->user->emergencyRequests()->open()->exists());
    }

    public function test_keeps_recently_updated_orders_open(): void
    {
        $driver = DriverProfile::factory()->withAmbulance()->create();
        $trip = $this->assignedTrip($driver, EmergencyStatus::ArriveAtPickup);
        $this->idleFor($trip, 119);

        $this->assertSame(0, EmergencyRequest::closeAbandoned());
        $this->assertSame(EmergencyStatus::ArriveAtPickup->value, $trip->refresh()->status);
    }

    public function test_keeps_a_long_drive_open_while_the_driver_still_sends_gps(): void
    {
        $driver = DriverProfile::factory()->withAmbulance()->create();
        $trip = $this->assignedTrip($driver, EmergencyStatus::EnRouteToHospital);
        $trip->tripLocationLogs()->create([
            'latitude' => -8.16,
            'longitude' => 113.71,
            'recorded_at' => now(),
        ]);
        $this->idleFor($trip, 180);

        $this->assertSame(0, EmergencyRequest::closeAbandoned());
        $this->assertSame(EmergencyStatus::EnRouteToHospital->value, $trip->refresh()->status);
    }

    public function test_leaves_finished_orders_alone(): void
    {
        $completed = EmergencyRequest::factory()->create(['status' => EmergencyStatus::Completed->value]);
        $this->idleFor($completed, 600);

        $this->assertSame(0, EmergencyRequest::closeAbandoned());
        $this->assertSame(EmergencyStatus::Completed->value, $completed->refresh()->status);
    }
}
