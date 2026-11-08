<?php

namespace Tests\Feature;

use App\Enums\DriverAvailability;
use App\Enums\EmergencyStatus;
use App\Models\DispatchLog;
use App\Models\DriverProfile;
use App\Models\EmergencyRequest;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverTripProgressApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(UserSeeder::class);
    }

    private function getDriverToken(): string
    {
        $driver = User::where('email', 'driver@cariambulan.id')->firstOrFail();
        return $driver->createToken('driver-test-token')->plainTextToken;
    }

    private function getPatientToken(): string
    {
        $patient = User::where('email', 'pasien@cariambulan.id')->firstOrFail();
        return $patient->createToken('patient-test-token')->plainTextToken;
    }

    public function test_driver_can_progress_trip_through_all_stages(): void
    {
        $driverToken = $this->getDriverToken();
        $patientToken = $this->getPatientToken();

        // 1. Driver goes online
        /** @var DriverProfile $driver */
        $driver = DriverProfile::firstOrFail();
        $driver->update([
            'availability_status' => DriverAvailability::Online->value,
            'current_latitude' => -6.2088,
            'current_longitude' => 106.8222,
            'location_updated_at' => now(),
        ]);

        // 2. Patient creates SOS
        $this->withHeader('Authorization', "Bearer $patientToken")
            ->postJson('/api/v1/emergency-requests', [
                'pickup_latitude' => -6.2088,
                'pickup_longitude' => 106.8222,
                'pickup_address' => 'Mall FX Sudirman',
            ])->assertStatus(201);

        // 3. Driver accepts offer
        app('auth')->forgetGuards();
        $offer = DispatchLog::where('driver_id', $driver->id)->latest('offered_at')->firstOrFail();
        $this->withHeader('Authorization', "Bearer $driverToken")
            ->postJson("/api/v1/driver/offers/{$offer->id}/accept")
            ->assertStatus(200);

        $this->assertEquals(DriverAvailability::Busy, $driver->fresh()->availability_status);

        // 4. Driver checks active trip
        app('auth')->forgetGuards();
        $currentTripRes = $this->withHeader('Authorization', "Bearer $driverToken")
            ->getJson('/api/v1/driver/trip/current');

        $currentTripRes->assertStatus(200)
            ->assertJsonPath('data.status', EmergencyStatus::DriverAssigned->value);

        $tripId = $currentTripRes->json('data.id');

        // Stage 1: En Route to Pickup
        app('auth')->forgetGuards();
        $this->withHeader('Authorization', "Bearer $driverToken")
            ->putJson('/api/v1/driver/trip/status', [
                'status' => 'en_route_to_pickup',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', EmergencyStatus::EnRouteToPickup->value);

        // Stage 2: Arrive at Pickup
        app('auth')->forgetGuards();
        $arriveRes = $this->withHeader('Authorization', "Bearer $driverToken")
            ->putJson('/api/v1/driver/trip/status', [
                'status' => 'arrive_at_pickup',
            ]);

        $arriveRes->assertStatus(200)
            ->assertJsonPath('data.status', EmergencyStatus::ArriveAtPickup->value);
        $this->assertNotNull($arriveRes->json('data.timeline.arrived_at_pickup_at'));

        // Stage 3: En Route to Hospital
        app('auth')->forgetGuards();
        $transitRes = $this->withHeader('Authorization', "Bearer $driverToken")
            ->putJson('/api/v1/driver/trip/status', [
                'status' => 'en_route_to_hospital',
            ]);

        $transitRes->assertStatus(200)
            ->assertJsonPath('data.status', EmergencyStatus::EnRouteToHospital->value);
        $this->assertNotNull($transitRes->json('data.timeline.picked_up_at'));

        // Stage 4: Completed
        app('auth')->forgetGuards();
        $completeRes = $this->withHeader('Authorization', "Bearer $driverToken")
            ->putJson('/api/v1/driver/trip/status', [
                'status' => 'completed',
            ]);

        $completeRes->assertStatus(200)
            ->assertJsonPath('data.status', EmergencyStatus::Completed->value);
        $this->assertNotNull($completeRes->json('data.timeline.completed_at'));

        // Driver availability is automatically released back to ONLINE
        $this->assertEquals(DriverAvailability::Online, $driver->fresh()->availability_status);

        // Active trip is now null
        app('auth')->forgetGuards();
        $this->withHeader('Authorization', "Bearer $driverToken")
            ->getJson('/api/v1/driver/trip/current')
            ->assertStatus(200)
            ->assertJson(['data' => null]);
    }

    public function test_driver_cannot_skip_trip_stages(): void
    {
        $driverToken = $this->getDriverToken();
        $patientToken = $this->getPatientToken();

        $driver = DriverProfile::firstOrFail();
        $driver->update([
            'availability_status' => DriverAvailability::Online->value,
            'current_latitude' => -6.2088,
            'current_longitude' => 106.8222,
            'location_updated_at' => now(),
        ]);

        $this->withHeader('Authorization', "Bearer $patientToken")
            ->postJson('/api/v1/emergency-requests', [
                'pickup_latitude' => -6.2088,
                'pickup_longitude' => 106.8222,
                'pickup_address' => 'Mall FX',
            ]);

        $offer = DispatchLog::where('driver_id', $driver->id)->latest('offered_at')->firstOrFail();
        app('auth')->forgetGuards();
        $this->withHeader('Authorization', "Bearer $driverToken")
            ->postJson("/api/v1/driver/offers/{$offer->id}/accept");

        // Attempting to jump directly from driver_assigned to completed must fail
        app('auth')->forgetGuards();
        $invalidRes = $this->withHeader('Authorization', "Bearer $driverToken")
            ->putJson('/api/v1/driver/trip/status', [
                'status' => 'completed',
            ]);

        $invalidRes->assertStatus(422)
            ->assertJsonStructure(['message']);
    }
}

