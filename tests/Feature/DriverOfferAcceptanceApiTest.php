<?php

namespace Tests\Feature;

use App\Enums\DispatchStatus;
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

class DriverOfferAcceptanceApiTest extends TestCase
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

    public function test_driver_can_accept_incoming_offer(): void
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
        $sosRes = $this->withHeader('Authorization', "Bearer $patientToken")
            ->postJson('/api/v1/emergency-requests', [
                'pickup_latitude' => -6.2088,
                'pickup_longitude' => 106.8222,
                'pickup_address' => 'Plaza Indonesia',
            ]);
        $sosRes->assertStatus(201);
        $requestId = $sosRes->json('data.id');

        // 3. Driver polls offer
        app('auth')->forgetGuards();
        $offerRes = $this->withHeader('Authorization', "Bearer $driverToken")
            ->getJson('/api/v1/driver/offers/current');
        $offerRes->assertStatus(200);
        $offerId = $offerRes->json('data.id');

        // 4. Driver accepts offer
        app('auth')->forgetGuards();
        $acceptRes = $this->withHeader('Authorization', "Bearer $driverToken")
            ->postJson("/api/v1/driver/offers/{$offerId}/accept");

        $acceptRes->assertStatus(200)
            ->assertJsonPath('data.status', EmergencyStatus::DriverAssigned->value)
            ->assertJsonPath('data.assigned_driver.id', $driver->id)
            ->assertJsonPath('data.ambulance.plate_number', 'B 1199 SGM');

        // Verify database state
        $this->assertEquals(DriverAvailability::Busy, $driver->fresh()->availability_status);
        $this->assertDatabaseHas('emergency_requests', [
            'id' => $requestId,
            'status' => EmergencyStatus::DriverAssigned->value,
            'driver_profile_id' => $driver->id,
        ]);
        $this->assertDatabaseHas('dispatch_logs', [
            'id' => $offerId,
            'status' => DispatchStatus::Accepted->value,
        ]);
    }

    public function test_driver_can_decline_incoming_offer(): void
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
                'pickup_address' => 'Bundaran HI',
            ])->assertStatus(201);

        app('auth')->forgetGuards();
        $offer = DispatchLog::where('driver_id', $driver->id)->latest('offered_at')->firstOrFail();

        $declineRes = $this->withHeader('Authorization', "Bearer $driverToken")
            ->postJson("/api/v1/driver/offers/{$offer->id}/decline");

        $declineRes->assertStatus(200);

        $this->assertEquals(DispatchStatus::Declined->value, $offer->fresh()->status);
        $this->assertNotNull($offer->fresh()->responded_at);
    }

    public function test_driver_cannot_accept_expired_offer(): void
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
                'pickup_address' => 'Sudirman',
            ])->assertStatus(201);

        $offer = DispatchLog::where('driver_id', $driver->id)->latest('offered_at')->firstOrFail();
        $offer->update(['offered_at' => now()->subSeconds(35)]);

        app('auth')->forgetGuards();
        $acceptRes = $this->withHeader('Authorization', "Bearer $driverToken")
            ->postJson("/api/v1/driver/offers/{$offer->id}/accept");

        $acceptRes->assertStatus(422);
    }
}

