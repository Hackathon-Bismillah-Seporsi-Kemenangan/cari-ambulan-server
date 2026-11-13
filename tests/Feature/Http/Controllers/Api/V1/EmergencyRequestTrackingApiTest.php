<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Enums\DriverAvailability;
use App\Enums\EmergencyStatus;
use App\Models\Ambulance;
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

class EmergencyRequestTrackingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(UserSeeder::class);
    }

    private function getPatient(): User
    {
        return User::where('email', 'pasien@cariambulan.id')->firstOrFail();
    }

    public function test_patient_can_view_emergency_requests_history(): void
    {
        $patient = $this->getPatient();
        Sanctum::actingAs($patient);

        EmergencyRequest::factory()->create([
            'user_id' => $patient->id,
            'status' => EmergencyStatus::Completed->value,
            'completed_at' => now(),
        ]);

        EmergencyRequest::factory()->create([
            'user_id' => $patient->id,
            'status' => EmergencyStatus::Cancelled->value,
            'cancelled_at' => now(),
            'cancel_reason' => 'Dibatalkan pemanggil',
        ]);

        $response = $this->getJson('/api/v1/emergency-requests');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'code',
                        'status',
                        'emergency_type',
                        'pickup_address',
                        'pickup' => ['address', 'latitude', 'longitude'],
                        'request_at',
                        'hospital',
                        'ambulance',
                        'driver',
                        'cancel_reason',
                        'cancelled_at',
                        'completed_at',
                    ],
                ],
            ]);
    }

    public function test_patient_can_view_emergency_request_details_with_search_status(): void
    {
        $patient = $this->getPatient();
        Sanctum::actingAs($patient);

        $emergency = EmergencyRequest::factory()->create([
            'user_id' => $patient->id,
            'status' => EmergencyStatus::SearchingDriver->value,
            'search_radius_km' => 3,
        ]);

        $response = $this->getJson("/api/v1/emergency-requests/{$emergency->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $emergency->id)
            ->assertJsonPath('data.status', 'searching_driver')
            ->assertJsonPath('data.search_radius_km', 3)
            ->assertJsonPath('data.search_exhausted', false)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'code',
                    'status',
                    'emergency_type',
                    'pickup',
                    'drivers_contacted',
                    'search_radius_km',
                    'radius_expands_in_seconds',
                    'search_exhausted',
                    'driver',
                    'ambulance',
                    'hospital',
                    'eta',
                    'nearby_ambulances',
                ],
            ]);
    }

    public function test_search_resource_exposes_location_age_seconds_for_silent_driver(): void
    {
        $patient = $this->getPatient();
        Sanctum::actingAs($patient);

        $hospital = Hospital::factory()->create(['latitude' => -6.2000, 'longitude' => 106.8100]);
        $driverUser = User::factory()->driver()->create();
        $driver = DriverProfile::factory()->create([
            'user_id' => $driverUser->id,
            'hospital_id' => $hospital->id,
            'availability_status' => DriverAvailability::Busy->value,
            'current_latitude' => -6.2080,
            'current_longitude' => 106.8220,
            'location_updated_at' => now()->subSeconds(25),
        ]);
        $ambulance = Ambulance::factory()->create([
            'driver_profile_id' => $driver->id,
            'hospital_id' => $hospital->id,
        ]);

        $emergency = EmergencyRequest::factory()->create([
            'user_id' => $patient->id,
            'driver_profile_id' => $driver->id,
            'ambulance_id' => $ambulance->id,
            'hospital_id' => $hospital->id,
            'status' => EmergencyStatus::EnRouteToPickup->value,
            'accepted_at' => now(),
            'pickup_latitude' => -6.2088,
            'pickup_longitude' => 106.8222,
        ]);

        $response = $this->getJson("/api/v1/emergency-requests/{$emergency->id}");

        $response->assertOk()
            ->assertJsonPath('data.status', 'en_route_to_pickup')
            ->assertJsonPath('data.ambulance.plate_number', $ambulance->plate_number);

        $this->assertEqualsWithDelta(25, $response->json('data.ambulance.location_age_seconds'), 3);
    }

    public function test_search_resource_stops_sharing_location_after_trip_completed(): void
    {
        $patient = $this->getPatient();
        Sanctum::actingAs($patient);

        $driver = DriverProfile::factory()->onlineAt(-6.2080, 106.8220)->create();
        $emergency = EmergencyRequest::factory()->create([
            'user_id' => $patient->id,
            'driver_profile_id' => $driver->id,
            'ambulance_id' => $driver->ambulance->id,
            'status' => EmergencyStatus::Completed->value,
            'completed_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/emergency-requests/{$emergency->id}");

        $response->assertOk()
            ->assertJsonPath('data.ambulance.latitude', null)
            ->assertJsonPath('data.ambulance.longitude', null)
            ->assertJsonPath('data.ambulance.location_age_seconds', null);
    }

    public function test_patient_can_widen_search_radius_manually(): void
    {
        $patient = $this->getPatient();
        Sanctum::actingAs($patient);

        $emergency = EmergencyRequest::factory()->create([
            'user_id' => $patient->id,
            'status' => EmergencyStatus::SearchingDriver->value,
            'search_radius_km' => 3,
        ]);

        $response = $this->postJson("/api/v1/emergency-requests/{$emergency->id}/radius");

        $response->assertOk()
            ->assertJsonPath('data.search_radius_km', 5);

        $this->assertEquals(5, $emergency->fresh()->search_radius_km);
    }

    public function test_patient_can_retry_exhausted_search(): void
    {
        $patient = $this->getPatient();
        Sanctum::actingAs($patient);

        $emergency = EmergencyRequest::factory()->create([
            'user_id' => $patient->id,
            'status' => EmergencyStatus::SearchingDriver->value,
            'search_radius_km' => 25,
            'search_exhausted_at' => now(),
        ]);

        $driver = DriverProfile::factory()->onlineAt(-6.2080, 106.8220)->create();
        DispatchLog::factory()->create([
            'emergency_request_id' => $emergency->id,
            'driver_id' => $driver->id,
            'status' => 'declined',
        ]);

        $response = $this->postJson("/api/v1/emergency-requests/{$emergency->id}/retry");

        $response->assertOk()
            ->assertJsonPath('data.search_exhausted', false)
            ->assertJsonPath('data.search_radius_km', 25);

        $this->assertNull($emergency->fresh()->search_exhausted_at);
        $this->assertDatabaseMissing('dispatch_logs', [
            'emergency_request_id' => $emergency->id,
            'status' => 'declined',
        ]);
    }

    public function test_unauthorized_user_cannot_view_or_cancel_others_emergency_request(): void
    {
        $patient = $this->getPatient();
        $otherUser = User::factory()->create();

        $emergency = EmergencyRequest::factory()->create([
            'user_id' => $patient->id,
            'status' => EmergencyStatus::SearchingDriver->value,
        ]);

        Sanctum::actingAs($otherUser);

        $this->getJson("/api/v1/emergency-requests/{$emergency->id}")
            ->assertNotFound();

        $this->postJson("/api/v1/emergency-requests/{$emergency->id}/cancel")
            ->assertNotFound();
    }
}

