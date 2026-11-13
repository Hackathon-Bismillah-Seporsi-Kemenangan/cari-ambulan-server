<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Driver;

use App\Enums\DispatchStatus;
use App\Enums\DriverAvailability;
use App\Enums\EmergencyStatus;
use App\Models\DispatchLog;
use App\Models\DriverProfile;
use App\Models\EmergencyRequest;
use App\Models\Hospital;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DriverTripLifecycleApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(UserSeeder::class);
    }

    private function getDriver(): DriverProfile
    {
        return DriverProfile::firstOrFail();
    }

    public function test_driver_can_view_trip_history(): void
    {
        $driver = $this->getDriver();
        $patient = User::where('email', 'pasien@cariambulan.id')->firstOrFail();

        EmergencyRequest::factory()->create([
            'user_id' => $patient->id,
            'driver_profile_id' => $driver->id,
            'status' => EmergencyStatus::Completed->value,
            'completed_at' => now(),
        ]);

        Sanctum::actingAs($driver->user);

        $response = $this->getJson('/api/v1/driver/trips');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'code',
                        'status',
                        'emergency_type',
                        'pickup_address',
                        'request_at',
                        'patient_name',
                        'hospital_name',
                        'completed_at',
                        'cancelled_at',
                        'cancel_reason',
                    ],
                ],
            ]);
    }

    public function test_driver_can_view_current_active_trip(): void
    {
        $driver = $this->getDriver();
        $patient = User::where('email', 'pasien@cariambulan.id')->firstOrFail();

        $trip = EmergencyRequest::factory()->create([
            'user_id' => $patient->id,
            'driver_profile_id' => $driver->id,
            'status' => EmergencyStatus::EnRouteToPickup->value,
            'accepted_at' => now(),
        ]);

        Sanctum::actingAs($driver->user);

        $response = $this->getJson('/api/v1/driver/trip');

        $response->assertOk()
            ->assertJsonPath('data.id', $trip->id)
            ->assertJsonPath('data.status', 'en_route_to_pickup');
    }

    public function test_driver_can_accept_and_progress_trip_stages(): void
    {
        $driver = $this->getDriver();
        $driver->update([
            'availability_status' => DriverAvailability::Online->value,
            'current_latitude' => -6.2088,
            'current_longitude' => 106.8222,
            'location_updated_at' => now(),
        ]);

        $patient = User::where('email', 'pasien@cariambulan.id')->firstOrFail();
        $emergency = EmergencyRequest::factory()->create([
            'user_id' => $patient->id,
            'status' => EmergencyStatus::SearchingDriver->value,
            'pickup_latitude' => -6.2088,
            'pickup_longitude' => 106.8222,
        ]);

        $offer = DispatchLog::factory()->create([
            'emergency_request_id' => $emergency->id,
            'driver_id' => $driver->id,
            'status' => DispatchStatus::Offered->value,
            'offered_at' => now(),
        ]);

        Sanctum::actingAs($driver->user);

        // 1. Accept
        $acceptRes = $this->postJson("/api/v1/driver/requests/{$emergency->id}/accept");
        $acceptRes->assertOk()->assertJsonPath('data.status', 'en_route_to_pickup');
        $this->assertEquals(DriverAvailability::Busy, $driver->fresh()->availability_status);

        // 2. Arrive at pickup
        $arriveRes = $this->postJson("/api/v1/driver/requests/{$emergency->id}/arrive");
        $arriveRes->assertOk()->assertJsonPath('data.status', 'arrive_at_pickup');
        $this->assertNotNull($emergency->fresh()->arrived_at_pickup_at);

        // 3. Start to hospital
        Hospital::query()->update(['latitude' => -7.5, 'longitude' => 110.0]);
        $nearHospital = Hospital::factory()->create(['name' => 'RS Medika', 'latitude' => -6.2005, 'longitude' => 106.8160]);

        $hospitalRes = $this->postJson("/api/v1/driver/requests/{$emergency->id}/start-to-hospital");
        $hospitalRes->assertOk()
            ->assertJsonPath('data.status', 'en_route_to_hospital')
            ->assertJsonPath('data.hospital.name', 'RS Medika');
        $this->assertEquals($nearHospital->id, $emergency->fresh()->hospital_id);

        // 4. Complete
        $completeRes = $this->postJson("/api/v1/driver/requests/{$emergency->id}/complete");
        $completeRes->assertOk()->assertJsonPath('data.status', 'completed');
        $this->assertNotNull($emergency->fresh()->completed_at);
        $this->assertEquals(DriverAvailability::Online, $driver->fresh()->availability_status);
    }

    public function test_driver_location_update_logs_coordinates_to_trip_location_logs(): void
    {
        $driver = $this->getDriver();
        $patient = User::where('email', 'pasien@cariambulan.id')->firstOrFail();

        $trip = EmergencyRequest::factory()->create([
            'user_id' => $patient->id,
            'driver_profile_id' => $driver->id,
            'status' => EmergencyStatus::EnRouteToPickup->value,
            'accepted_at' => now(),
        ]);

        Sanctum::actingAs($driver->user);

        $response = $this->postJson('/api/v1/driver/location', [
            'latitude' => -6.20900000,
            'longitude' => 106.82300000,
        ]);

        $response->assertNoContent();

        $this->assertDatabaseHas('trip_location_logs', [
            'emergency_request_id' => $trip->id,
            'latitude' => -6.20900000,
            'longitude' => 106.82300000,
        ]);
    }

    public function test_driver_cannot_skip_stages(): void
    {
        $driver = $this->getDriver();
        $patient = User::where('email', 'pasien@cariambulan.id')->firstOrFail();

        $trip = EmergencyRequest::factory()->create([
            'user_id' => $patient->id,
            'driver_profile_id' => $driver->id,
            'status' => EmergencyStatus::EnRouteToPickup->value,
            'accepted_at' => now(),
        ]);

        Sanctum::actingAs($driver->user);

        $response = $this->postJson("/api/v1/driver/requests/{$trip->id}/complete");

        $response->assertStatus(409)
            ->assertJsonPath('message', 'Status order tidak sesuai untuk langkah ini.');
    }
}
