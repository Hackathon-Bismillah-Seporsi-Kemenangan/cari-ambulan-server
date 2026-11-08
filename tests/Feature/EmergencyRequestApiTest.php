<?php

namespace Tests\Feature;

use App\Enums\DriverAvailability;
use App\Enums\EmergencyStatus;
use App\Models\DriverProfile;
use App\Models\EmergencyRequest;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmergencyRequestApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(UserSeeder::class);
    }

    private function getPatientToken(): string
    {
        $patient = User::where('email', 'pasien@cariambulan.id')->firstOrFail();
        return $patient->createToken('patient-test-token')->plainTextToken;
    }

    private function getDriverToken(): string
    {
        $driver = User::where('email', 'driver@cariambulan.id')->firstOrFail();
        return $driver->createToken('driver-test-token')->plainTextToken;
    }

    public function test_patient_can_create_emergency_request(): void
    {
        $token = $this->getPatientToken();

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/emergency-requests', [
                'is_for_self' => true,
                'emergency_type' => 'cardiac',
                'severity_level' => 'critical',
                'patient_notes' => 'Pasien tidak sadarkan diri di lobi gedung',
                'pickup_latitude' => -6.2088,
                'pickup_longitude' => 106.8222,
                'pickup_address' => 'Plaza Indonesia, Jakarta Pusat',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'searching_driver')
            ->assertJsonPath('data.pickup.address', 'Plaza Indonesia, Jakarta Pusat')
            ->assertJsonPath('data.pickup.latitude', -6.2088)
            ->assertJsonPath('data.emergency_type', 'cardiac');

        $this->assertDatabaseHas('emergency_requests', [
            'pickup_address' => 'Plaza Indonesia, Jakarta Pusat',
            'status' => EmergencyStatus::SearchingDriver->value,
        ]);
    }

    public function test_cannot_create_duplicate_active_emergency_request(): void
    {
        $token = $this->getPatientToken();

        // First request succeeds
        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/emergency-requests', [
                'pickup_latitude' => -6.2088,
                'pickup_longitude' => 106.8222,
                'pickup_address' => 'Alamat 1',
            ])->assertStatus(201);

        // Second request triggers 409 Conflict
        $duplicateRes = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/emergency-requests', [
                'pickup_latitude' => -6.2088,
                'pickup_longitude' => 106.8222,
                'pickup_address' => 'Alamat 2',
            ]);

        $duplicateRes->assertStatus(409)
            ->assertJsonStructure(['message', 'data']);
    }

    public function test_patient_can_get_current_emergency_request(): void
    {
        $token = $this->getPatientToken();

        // 1. When no active request exists, data is null
        $emptyRes = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/emergency-requests/current');

        $emptyRes->assertStatus(200)
            ->assertJson(['data' => null]);

        // 2. Create an emergency request
        $createRes = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/emergency-requests', [
                'pickup_latitude' => -6.2088,
                'pickup_longitude' => 106.8222,
                'pickup_address' => 'Grand Indonesia',
            ]);

        $requestId = $createRes->json('data.id');

        // 3. Current endpoint returns the active request
        $currentRes = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/emergency-requests/current');

        $currentRes->assertStatus(200)
            ->assertJsonPath('data.id', $requestId)
            ->assertJsonPath('data.pickup.address', 'Grand Indonesia');
    }

    public function test_driver_can_poll_incoming_emergency_offer(): void
    {
        $driverToken = $this->getDriverToken();
        $patientToken = $this->getPatientToken();

        // 1. Set driver online
        $driver = DriverProfile::firstOrFail();
        $driver->update([
            'availability_status' => DriverAvailability::Online->value,
            'current_latitude' => -6.2080,
            'current_longitude' => 106.8220,
            'location_updated_at' => now(),
        ]);

        // 2. Patient creates SOS nearby
        $sosRes = $this->withHeader('Authorization', "Bearer $patientToken")
            ->postJson('/api/v1/emergency-requests', [
                'pickup_latitude' => -6.2088,
                'pickup_longitude' => 106.8222,
                'pickup_address' => 'Bundaran HI',
                'emergency_type' => 'trauma',
                'severity_level' => 'severe',
                'patient_notes' => 'Kecelakaan motor',
            ]);

        $sosRes->assertStatus(201);
        $requestId = $sosRes->json('data.id');

        // 3. Driver polls current offers
        app('auth')->forgetGuards();
        $offerRes = $this->withHeader('Authorization', "Bearer $driverToken")
            ->getJson('/api/v1/driver/offers/current');

        $offerRes->assertStatus(200)
            ->assertJsonPath('data.emergency_request_id', $requestId)
            ->assertJsonPath('data.emergency_type', 'trauma')
            ->assertJsonPath('data.pickup.address', 'Bundaran HI')
            ->assertJsonPath('data.patient.name', 'Siti Rahmawati');

        $this->assertLessThanOrEqual(30, $offerRes->json('data.seconds_remaining'));
    }

    public function test_patient_can_cancel_searching_emergency_request(): void
    {
        $token = $this->getPatientToken();

        $createRes = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/emergency-requests', [
                'pickup_latitude' => -6.2088,
                'pickup_longitude' => 106.8222,
                'pickup_address' => 'Plaza Semanggi',
            ]);

        $requestId = $createRes->json('data.id');

        $cancelRes = $this->withHeader('Authorization', "Bearer $token")
            ->postJson("/api/v1/emergency-requests/{$requestId}/cancel", [
                'reason' => 'Sudah dapat kendaraan lain',
            ]);

        $cancelRes->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancel_reason', 'Sudah dapat kendaraan lain');

        $this->assertDatabaseHas('emergency_requests', [
            'id' => $requestId,
            'status' => EmergencyStatus::Cancelled->value,
            'cancel_reason' => 'Sudah dapat kendaraan lain',
        ]);
    }
}
