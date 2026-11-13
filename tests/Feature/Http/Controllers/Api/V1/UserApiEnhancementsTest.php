<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Enums\DispatchStatus;
use App\Enums\DriverAvailability;
use App\Enums\EmergencyStatus;
use App\Models\Ambulance;
use App\Models\DispatchLog;
use App\Models\DriverProfile;
use App\Models\EmergencyRequest;
use App\Models\Hospital;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserApiEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsDriver(DriverProfile $driver): DriverProfile
    {
        Sanctum::actingAs($driver->user);

        return $driver;
    }

    public function test_returns_null_for_driver_ambulance_hospital_and_eta_while_searching(): void
    {
        $user = User::factory()->withRole('user')->create();
        $emergency = EmergencyRequest::factory()->for($user)->create([
            'status' => EmergencyStatus::SearchingDriver->value,
            'driver_profile_id' => null,
            'ambulance_id' => null,
            'hospital_id' => null,
        ]);
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/emergency-requests/{$emergency->id}");

        $response->assertOk()
            ->assertJsonPath('data.driver', null)
            ->assertJsonPath('data.ambulance', null)
            ->assertJsonPath('data.hospital', null)
            ->assertJsonPath('data.eta', null);
    }

    public function test_returns_driver_ambulance_hospital_and_eta_once_driver_is_assigned(): void
    {
        $user = User::factory()->withRole('user')->create();
        $hospital = Hospital::factory()->create(['name' => 'RS Siloam Semanggi']);
        $driverUser = User::factory()->driver()->create([
            'full_name' => 'Budi Santoso',
            'phone' => '081299998888',
        ]);
        $driver = DriverProfile::factory()->create([
            'user_id' => $driverUser->id,
            'hospital_id' => $hospital->id,
            'current_latitude' => -6.2200,
            'current_longitude' => 106.8100,
            'location_updated_at' => now(),
            'availability_status' => DriverAvailability::Busy->value,
        ]);
        $ambulance = Ambulance::factory()->create([
            'driver_profile_id' => $driver->id,
            'hospital_id' => $hospital->id,
            'plate_number' => 'B 1199 SGM',
            'vehicle_type' => 'ALS',
        ]);

        $emergency = EmergencyRequest::factory()->for($user)->create([
            'status' => EmergencyStatus::EnRouteToPickup->value,
            'pickup_latitude' => -6.2250,
            'pickup_longitude' => 106.8150,
            'driver_profile_id' => $driver->id,
            'ambulance_id' => $ambulance->id,
            'hospital_id' => $hospital->id,
        ]);
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/emergency-requests/{$emergency->id}");

        $response->assertOk()
            ->assertJsonPath('data.driver.name', 'Budi Santoso')
            ->assertJsonPath('data.driver.phone', '081299998888')
            ->assertJsonPath('data.driver.photo_url', null)
            ->assertJsonPath('data.driver.rating', null)
            ->assertJsonPath('data.driver.trip_count', null)
            ->assertJsonPath('data.ambulance.plate_number', 'B 1199 SGM')
            ->assertJsonPath('data.ambulance.vehicle_type', 'ALS')
            ->assertJsonPath('data.ambulance.latitude', -6.2200)
            ->assertJsonPath('data.ambulance.longitude', 106.8100)
            ->assertJsonPath('data.hospital.name', 'RS Siloam Semanggi');

        $this->assertGreaterThan(0, $response->json('data.eta.minutes'));
        $this->assertGreaterThan(0, $response->json('data.eta.distance_km'));
    }

    public function test_allows_patient_to_cancel_with_reason_while_ambulance_is_en_route_to_pickup(): void
    {
        $user = User::factory()->withRole('user')->create();
        $driver = DriverProfile::factory()->onlineAt(-6.2200, 106.8100)->create([
            'availability_status' => DriverAvailability::Busy->value,
        ]);
        $emergency = EmergencyRequest::factory()->for($user)->create([
            'status' => EmergencyStatus::EnRouteToPickup->value,
            'driver_profile_id' => $driver->id,
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/emergency-requests/{$emergency->id}/cancel", [
            'reason' => 'Kondisi membaik',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancel_reason', 'Kondisi membaik');

        $this->assertSame(EmergencyStatus::Cancelled->value, $emergency->fresh()->status);
        $this->assertSame('Kondisi membaik', $emergency->fresh()->cancel_reason);
        $this->assertSame(DriverAvailability::Online, $driver->fresh()->availability_status);
    }

    public function test_shows_cancelled_trip_on_get_driver_trip_so_driver_app_can_close_screen(): void
    {
        $driver = DriverProfile::factory()->onlineAt(-6.2200, 106.8100)->create([
            'availability_status' => DriverAvailability::Online->value,
        ]);
        $user = User::factory()->withRole('user')->create();
        $emergency = EmergencyRequest::factory()->for($user)->create([
            'status' => EmergencyStatus::Cancelled->value,
            'driver_profile_id' => $driver->id,
            'cancelled_at' => now(),
            'cancel_reason' => 'Salah lokasi',
        ]);

        $this->actingAsDriver($driver);

        $response = $this->getJson('/api/v1/driver/trip');

        $response->assertOk()
            ->assertJsonPath('data.id', $emergency->id)
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancel_reason', 'Salah lokasi');
    }

    public function test_rejects_cancellation_once_ambulance_arrives_at_pickup(): void
    {
        $user = User::factory()->withRole('user')->create();
        $emergency = EmergencyRequest::factory()->for($user)->create([
            'status' => EmergencyStatus::ArriveAtPickup->value,
        ]);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/emergency-requests/{$emergency->id}/cancel", [
            'reason' => 'Pakai kendaraan lain',
        ])->assertConflict();

        $this->assertSame(EmergencyStatus::ArriveAtPickup->value, $emergency->fresh()->status);
    }

    public function test_returns_the_user_past_emergency_requests_up_to_50_newest_first(): void
    {
        $user = User::factory()->withRole('user')->create();
        $otherUser = User::factory()->withRole('user')->create();

        EmergencyRequest::factory()->for($otherUser)->create(['request_code' => 'OTHER-01']);

        $hospital = Hospital::factory()->create(['name' => 'RS Mitra Keluarga']);
        $driverUser = User::factory()->driver()->create(['full_name' => 'Agus Driver']);
        $driver = DriverProfile::factory()->create([
            'user_id' => $driverUser->id,
            'hospital_id' => $hospital->id,
        ]);
        $ambulance = Ambulance::factory()->create([
            'driver_profile_id' => $driver->id,
            'plate_number' => 'B 9999 ABC',
        ]);

        $req1 = EmergencyRequest::factory()->for($user)->create([
            'request_code' => 'CA-HIST01',
            'status' => EmergencyStatus::Completed->value,
            'emergency_type' => 'Sesak Nafas',
            'pickup_address' => 'Jl. Tebet Raya No. 5',
            'request_at' => now()->subDay(),
            'hospital_id' => $hospital->id,
            'ambulance_id' => $ambulance->id,
            'driver_profile_id' => $driver->id,
        ]);

        $req2 = EmergencyRequest::factory()->for($user)->create([
            'request_code' => 'CA-HIST02',
            'status' => EmergencyStatus::Cancelled->value,
            'emergency_type' => 'Kecelakaan',
            'pickup_address' => 'Jl. Gatot Subroto No. 10',
            'request_at' => now(),
            'cancel_reason' => 'Kondisi membaik',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/emergency-requests');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $req2->id)
            ->assertJsonPath('data.0.code', 'CA-HIST02')
            ->assertJsonPath('data.0.status', 'cancelled')
            ->assertJsonPath('data.0.pickup_address', 'Jl. Gatot Subroto No. 10')
            ->assertJsonPath('data.1.id', $req1->id)
            ->assertJsonPath('data.1.code', 'CA-HIST01')
            ->assertJsonPath('data.1.hospital.name', 'RS Mitra Keluarga')
            ->assertJsonPath('data.1.ambulance.plate_number', 'B 9999 ABC')
            ->assertJsonPath('data.1.driver.name', 'Agus Driver');
    }

    public function test_returns_null_on_get_me_medical_profile_when_no_profile_exists(): void
    {
        $user = User::factory()->withRole('user')->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/me/medical-profile')
            ->assertOk()
            ->assertExactJson(['data' => null]);
    }

    public function test_creates_and_updates_medical_profile_with_string_blood_type_and_json_arrays(): void
    {
        $user = User::factory()->withRole('user')->create();
        Sanctum::actingAs($user);

        $payload = [
            'blood_type' => 'O+',
            'allergies' => ['Penisilin', 'Kacang'],
            'medical_conditions' => ['Asma', 'Hipertensi'],
            'birth_date' => '1995-08-17',
            'gender' => null,
            'weight_kg' => 68.5,
            'height_cm' => 172.0,
            'emergency_contact_name' => 'Dewi Sartika',
            'emergency_contact_relation' => 'Istri',
            'emergency_contact_phone' => '081234567899',
            'insurance' => 'BPJS Kesehatan',
            'share_on_sos' => true,
        ];

        $response = $this->putJson('/api/v1/me/medical-profile', $payload);

        $response->assertOk()
            ->assertJsonPath('data.blood_type', 'O+')
            ->assertJsonPath('data.allergies', ['Penisilin', 'Kacang'])
            ->assertJsonPath('data.medical_conditions', ['Asma', 'Hipertensi'])
            ->assertJsonPath('data.weight_kg', 68.5)
            ->assertJsonPath('data.height_cm', 172)
            ->assertJsonPath('data.emergency_contact_name', 'Dewi Sartika')
            ->assertJsonPath('data.emergency_contact_relation', 'Istri')
            ->assertJsonPath('data.emergency_contact_phone', '081234567899')
            ->assertJsonPath('data.insurance', 'BPJS Kesehatan')
            ->assertJsonPath('data.share_on_sos', true);

        $this->getJson('/api/v1/me/medical-profile')
            ->assertOk()
            ->assertJsonPath('data.blood_type', 'O+')
            ->assertJsonPath('data.allergies', ['Penisilin', 'Kacang']);
    }

    public function test_lists_and_saves_user_addresses(): void
    {
        $user = User::factory()->withRole('user')->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/me/addresses')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $response = $this->postJson('/api/v1/me/addresses', [
            'label' => 'Rumah',
            'address' => 'Jl. Diponegoro No. 12, Menteng',
            'latitude' => -6.1985,
            'longitude' => 106.8450,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.label', 'Rumah')
            ->assertJsonPath('data.address', 'Jl. Diponegoro No. 12, Menteng')
            ->assertJsonPath('data.latitude', -6.1985)
            ->assertJsonPath('data.longitude', 106.8450);

        $this->assertNotNull($response->json('data.id'));

        $this->getJson('/api/v1/me/addresses')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.label', 'Rumah');
    }

    public function test_updates_user_full_name_and_phone_and_returns_user_resource_format(): void
    {
        $user = User::factory()->withRole('user')->create([
            'full_name' => 'Original Name',
            'phone' => '0811111111',
        ]);
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/v1/me', [
            'full_name' => 'Updated Name',
            'phone' => '0822222222',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.full_name', 'Updated Name')
            ->assertJsonPath('data.phone', '0822222222')
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.roles', ['user']);

        $this->assertSame('Updated Name', $user->fresh()->full_name);
        $this->assertSame('0822222222', $user->fresh()->phone);
    }

    public function test_restricts_initial_search_to_3_km_and_ignores_drivers_further_out(): void
    {
        $user = User::factory()->withRole('user')->create();
        $farDriver = DriverProfile::factory()->onlineAt(-6.2600, 106.8125)->create(); // ~4 km away
        $emergency = EmergencyRequest::factory()->for($user)->create([
            'pickup_latitude' => -6.2245,
            'pickup_longitude' => 106.8125,
            'search_radius_km' => 3,
        ]);

        $log = $emergency->offerToNextDriver();

        $this->assertNull($log);
        $this->assertSame(0, $emergency->dispatchLogs()->count());
    }

    public function test_widens_radius_on_post_emergency_requests_radius_and_offers_to_newly_covered_driver(): void
    {
        $user = User::factory()->withRole('user')->create();
        $midDriver = DriverProfile::factory()->onlineAt(-6.2600, 106.8125)->create(); // ~4 km away
        $emergency = EmergencyRequest::factory()->for($user)->create([
            'pickup_latitude' => -6.2245,
            'pickup_longitude' => 106.8125,
            'search_radius_km' => 3,
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/emergency-requests/{$emergency->id}/radius");

        $response->assertOk()
            ->assertJsonPath('data.search_radius_km', 5);

        $this->assertSame(5, $emergency->fresh()->search_radius_km);
        $this->assertTrue($emergency->dispatchLogs()->where('driver_id', $midDriver->id)->exists());
    }

    public function test_restarts_search_at_25_km_and_resets_declined_offers_on_post_emergency_requests_retry(): void
    {
        $user = User::factory()->withRole('user')->create();
        $driver = DriverProfile::factory()->onlineAt(-6.2300, 106.8125)->create();
        $emergency = EmergencyRequest::factory()->for($user)->create([
            'pickup_latitude' => -6.2245,
            'pickup_longitude' => 106.8125,
            'search_radius_km' => 25,
            'search_exhausted_at' => now(),
        ]);
        DispatchLog::factory()->for($emergency)->create([
            'driver_id' => $driver->id,
            'status' => DispatchStatus::Declined->value,
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/emergency-requests/{$emergency->id}/retry");

        $response->assertOk()
            ->assertJsonPath('data.search_radius_km', 25)
            ->assertJsonPath('data.search_exhausted', false);

        $this->assertNull($emergency->fresh()->search_exhausted_at);
        $this->assertSame('offered', $emergency->dispatchLogs()->where('driver_id', $driver->id)->first()->status);
    }

    public function test_widens_radius_automatically_in_redispatch_waiting_every_90_seconds(): void
    {
        $user = User::factory()->withRole('user')->create();
        $emergency = EmergencyRequest::factory()->for($user)->create([
            'search_radius_km' => 3,
            'radius_changed_at' => now()->subSeconds(95),
        ]);

        EmergencyRequest::redispatchWaiting();

        $this->assertSame(5, $emergency->fresh()->search_radius_km);
    }

    public function test_sets_search_exhausted_at_when_at_25_km_with_no_drivers_available(): void
    {
        $user = User::factory()->withRole('user')->create();
        $emergency = EmergencyRequest::factory()->for($user)->create([
            'search_radius_km' => 25,
            'search_exhausted_at' => null,
        ]);

        $emergency->offerToNextDriver();

        $this->assertNotNull($emergency->fresh()->search_exhausted_at);
        $this->assertTrue($emergency->fresh()->isSearchExhausted());
    }
}
