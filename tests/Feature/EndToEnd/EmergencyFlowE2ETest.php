<?php

namespace Tests\Feature\EndToEnd;

use App\Enums\DriverAvailability;
use App\Enums\EmergencyStatus;
use App\Models\Ambulance;
use App\Models\DispatchLog;
use App\Models\DriverProfile;
use App\Models\EmergencyRequest;
use App\Models\Hospital;
use App\Models\User;
use App\Models\UserMedicalProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmergencyFlowE2ETest extends TestCase
{
    use RefreshDatabase;

    private function actingAsDriver(DriverProfile $driver): DriverProfile
    {
        Sanctum::actingAs($driver->user);

        return $driver;
    }

    public function test_executes_full_patient_profile_medical_id_and_emergency_address_preparedness_flow(): void
    {
        $patient = User::factory()->withRole('user')->create([
            'full_name' => 'Siti Aisyah',
            'email' => 'siti@example.com',
            'phone' => '081211112222',
        ]);
        Sanctum::actingAs($patient);

        // 1. Task 6: Edit profile via PUT /api/v1/me
        $updateProfileResp = $this->putJson('/api/v1/me', [
            'full_name' => 'Siti Aisyah, S.Kom',
            'phone' => '081299990000',
        ]);
        $updateProfileResp->assertOk()
            ->assertJsonPath('data.full_name', 'Siti Aisyah, S.Kom')
            ->assertJsonPath('data.phone', '081299990000')
            ->assertJsonPath('data.roles', ['user']);

        // Verify GET /api/v1/auth/me matches
        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.full_name', 'Siti Aisyah, S.Kom')
            ->assertJsonPath('data.phone', '081299990000');

        // 2. Task 4: Medical profile starts null
        $this->getJson('/api/v1/me/medical-profile')
            ->assertOk()
            ->assertExactJson(['data' => null]);

        // Save Medical ID via PUT /api/v1/me/medical-profile
        $saveMedicalResp = $this->putJson('/api/v1/me/medical-profile', [
            'blood_type' => 'O+',
            'allergies' => ['Penisilin', 'Aspirin'],
            'medical_conditions' => ['Asma Akut'],
            'birth_date' => '1998-05-12',
            'gender' => 'Perempuan',
            'weight_kg' => 56.5,
            'height_cm' => 163.0,
            'emergency_contact_name' => 'Rahmat Hidayat',
            'emergency_contact_relation' => 'Suami',
            'emergency_contact_phone' => '081388887777',
            'insurance' => 'BPJS Kesehatan Mandiri',
            'share_on_sos' => true,
        ]);

        $saveMedicalResp->assertOk()
            ->assertJsonPath('data.blood_type', 'O+')
            ->assertJsonPath('data.allergies', ['Penisilin', 'Aspirin'])
            ->assertJsonPath('data.medical_conditions', ['Asma Akut'])
            ->assertJsonPath('data.weight_kg', 56.5)
            ->assertJsonPath('data.height_cm', 163)
            ->assertJsonPath('data.emergency_contact_name', 'Rahmat Hidayat')
            ->assertJsonPath('data.emergency_contact_phone', '081388887777');

        $this->assertGreaterThan(0, $saveMedicalResp->json('data.age'));

        // Subsequent GET returns the saved profile
        $this->getJson('/api/v1/me/medical-profile')
            ->assertOk()
            ->assertJsonPath('data.blood_type', 'O+')
            ->assertJsonPath('data.allergies.0', 'Penisilin');

        // 3. Task 5: Emergency addresses
        $this->getJson('/api/v1/me/addresses')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $addr1 = $this->postJson('/api/v1/me/addresses', [
            'label' => 'Rumah',
            'address' => 'Jl. Kebon Sirih No. 10, Jakarta Pusat',
            'latitude' => -6.1820,
            'longitude' => 106.8280,
        ]);
        $addr1->assertCreated()
            ->assertJsonPath('data.label', 'Rumah');

        $addr2 = $this->postJson('/api/v1/me/addresses', [
            'label' => 'Kantor',
            'address' => 'Gedung Bursa Efek Indonesia, SCBD',
            'latitude' => -6.2245,
            'longitude' => 106.8090,
        ]);
        $addr2->assertCreated()
            ->assertJsonPath('data.label', 'Kantor');

        $listResp = $this->getJson('/api/v1/me/addresses');
        $listResp->assertOk()->assertJsonCount(2, 'data');

        // Delete one address
        $delResp = $this->deleteJson('/api/v1/me/addresses/'.$addr2->json('data.id'));
        $delResp->assertOk();
        $this->getJson('/api/v1/me/addresses')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.label', 'Rumah');
    }

    public function test_executes_full_emergency_dispatch_90s_radius_ladder_tracking_and_cancellation_with_reason(): void
    {
        // Setup Hospital & Driver (located ~4.2 km away from pickup point)
        $hospital = Hospital::factory()->create([
            'name' => 'RSUP Fatmawati',
            'latitude' => -6.2500,
            'longitude' => 106.8100,
        ]);
        $driverUser = User::factory()->driver()->create([
            'full_name' => 'Budi Santoso',
            'phone' => '081299991199',
        ]);
        $driver = DriverProfile::factory()->create([
            'user_id' => $driverUser->id,
            'hospital_id' => $hospital->id,
            'current_latitude' => -6.2620,
            'current_longitude' => 106.8125, // ~4.17 km south of pickup (-6.2245, 106.8125)
            'location_updated_at' => now(),
            'availability_status' => DriverAvailability::Online->value,
        ]);
        Ambulance::factory()->create([
            'driver_profile_id' => $driver->id,
            'hospital_id' => $hospital->id,
            'plate_number' => 'B 1199 SGM',
            'vehicle_type' => 'Ambulans ALS',
        ]);

        // Patient setup
        $patient = User::factory()->withRole('user')->create([
            'full_name' => 'Ahmad Dahlan',
            'phone' => '081233334444',
        ]);
        UserMedicalProfile::factory()->create([
            'user_id' => $patient->id,
            'blood_type' => 'B+',
            'allergies' => ['Seafood'],
            'medical_conditions' => ['Hipertensi'],
        ]);
        Sanctum::actingAs($patient);

        // 1. Patient creates emergency call (Task 7 radius starts at 3 km)
        $storeResp = $this->postJson('/api/v1/emergency-requests', [
            'emergency_type' => 'Sesak Nafas',
            'severity_level' => 'high',
            'is_for_self' => true,
            'patient_notes' => 'Pasien sesak nafas mendadak di kantor.',
            'pickup_latitude' => -6.2245,
            'pickup_longitude' => 106.8125,
            'pickup_address' => 'Gedung Artha Graha Lt. 12',
        ]);
        $storeResp->assertCreated();
        $emergencyId = $storeResp->json('data.id');

        // Task 1: Check search response while searching -> assigned details are null
        $searchResp = $this->getJson("/api/v1/emergency-requests/{$emergencyId}");
        $searchResp->assertOk()
            ->assertJsonPath('data.status', 'searching_driver')
            ->assertJsonPath('data.search_radius_km', 3)
            ->assertJsonPath('data.search_exhausted', false)
            ->assertJsonPath('data.driver', null)
            ->assertJsonPath('data.ambulance', null)
            ->assertJsonPath('data.hospital', null)
            ->assertJsonPath('data.eta', null);

        // Driver is ~4.2 km away, so at 3 km radius no offer is made yet
        $this->assertSame(0, DispatchLog::where('emergency_request_id', $emergencyId)->count());

        // 2. Task 7: Time advances 95 seconds -> radius expands to 5 km during redispatch
        $this->travel(95)->seconds();
        $driver->update(['location_updated_at' => now()]);

        // The scheduler (every second) runs redispatchWaiting()
        EmergencyRequest::redispatchWaiting();
        $pollResp = $this->getJson("/api/v1/emergency-requests/{$emergencyId}");
        $pollResp->assertOk()
            ->assertJsonPath('data.search_radius_km', 5);

        // Now driver receives the offer!
        $this->assertTrue(DispatchLog::where('emergency_request_id', $emergencyId)->where('driver_id', $driver->id)->exists());

        // 3. Driver sees offer via GET /api/v1/driver/offers/current
        $this->actingAsDriver($driver);
        $offerResp = $this->getJson('/api/v1/driver/offers/current');
        $offerResp->assertOk()
            ->assertJsonPath('data.request.id', $emergencyId)
            ->assertJsonPath('data.request.reporter.name', 'Ahmad Dahlan')
            ->assertJsonPath('data.request.medical.blood_type', 'B+')
            ->assertJsonPath('data.request.medical.allergies.0', 'Seafood');

        // 4. Driver accepts offer via POST /api/v1/driver/requests/{id}/accept
        $acceptResp = $this->postJson("/api/v1/driver/requests/{$emergencyId}/accept");
        $acceptResp->assertOk()
            ->assertJsonPath('data.status', 'en_route_to_pickup');

        $this->assertSame(DriverAvailability::Busy, $driver->fresh()->availability_status);

        // 5. Task 1: Patient polls search response -> sees assigned driver, plate, coordinates, ETA
        Sanctum::actingAs($patient);
        $trackingResp = $this->getJson('/api/v1/emergency-requests/current');
        $trackingResp->assertOk()
            ->assertJsonPath('data.status', 'en_route_to_pickup')
            ->assertJsonPath('data.driver.name', 'Budi Santoso')
            ->assertJsonPath('data.driver.phone', '081299991199')
            ->assertJsonPath('data.ambulance.plate_number', 'B 1199 SGM')
            ->assertJsonPath('data.ambulance.vehicle_type', 'Ambulans ALS')
            ->assertJsonPath('data.ambulance.latitude', -6.2620)
            ->assertJsonPath('data.ambulance.longitude', 106.8125)
            ->assertJsonPath('data.hospital.name', 'RSUP Fatmawati');

        $this->assertGreaterThan(0, $trackingResp->json('data.eta.minutes'));
        $this->assertGreaterThan(0, $trackingResp->json('data.eta.distance_km'));

        // 6. Task 2: Patient cancels while ambulance is en route with reason
        $cancelResp = $this->postJson("/api/v1/emergency-requests/{$emergencyId}/cancel", [
            'reason' => 'Kondisi membaik',
        ]);
        $cancelResp->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancel_reason', 'Kondisi membaik');

        // Driver availability is automatically reset to online
        $this->assertSame(DriverAvailability::Online, $driver->fresh()->availability_status);

        // 7. Task 2: Driver polls GET /api/v1/driver/trip -> receives cancelled trip so screen closes
        $this->actingAsDriver($driver);
        $driverTripResp = $this->getJson('/api/v1/driver/trip');
        $driverTripResp->assertOk()
            ->assertJsonPath('data.id', $emergencyId)
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancel_reason', 'Kondisi membaik');

        // 8. Task 3: Patient checks Riwayat (GET /api/v1/emergency-requests)
        Sanctum::actingAs($patient);
        $riwayatResp = $this->getJson('/api/v1/emergency-requests');
        $riwayatResp->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $emergencyId)
            ->assertJsonPath('data.0.status', 'cancelled')
            ->assertJsonPath('data.0.cancel_reason', 'Kondisi membaik')
            ->assertJsonPath('data.0.driver.name', 'Budi Santoso')
            ->assertJsonPath('data.0.ambulance.plate_number', 'B 1199 SGM')
            ->assertJsonPath('data.0.hospital.name', 'RSUP Fatmawati');
    }

    public function test_enforces_that_patient_cannot_cancel_after_arrival_at_pickup_and_finishes_full_trip_to_hospital(): void
    {
        $hospital = Hospital::factory()->create(['name' => 'RS Cipto Mangunkusumo']);
        $driverUser = User::factory()->driver()->create(['full_name' => 'Hendra Supir']);
        $driver = DriverProfile::factory()->onlineAt(-6.2000, 106.8200)->create([
            'user_id' => $driverUser->id,
            'hospital_id' => $hospital->id,
        ]);
        Ambulance::factory()->create([
            'driver_profile_id' => $driver->id,
            'hospital_id' => $hospital->id,
            'plate_number' => 'B 1234 IGD',
        ]);

        $patient = User::factory()->withRole('user')->create();
        Sanctum::actingAs($patient);

        // Patient creates request near driver
        $storeResp = $this->postJson('/api/v1/emergency-requests', [
            'emergency_type' => 'Kecelakaan',
            'severity_level' => 'high',
            'is_for_self' => false,
            'patient_notes' => 'Tabrakan motor.',
            'pickup_latitude' => -6.2050,
            'pickup_longitude' => 106.8220,
            'pickup_address' => 'Jl. Diponegoro No. 8',
        ]);
        $emergencyId = $storeResp->json('data.id');

        // Driver accepts
        $this->actingAsDriver($driver);
        $this->postJson("/api/v1/driver/requests/{$emergencyId}/accept")->assertOk();

        // Driver arrives at pickup
        $arriveResp = $this->postJson("/api/v1/driver/requests/{$emergencyId}/arrive");
        $arriveResp->assertOk()->assertJsonPath('data.status', 'arrive_at_pickup');

        // Task 2: Patient attempts to cancel -> REJECTED with 409
        Sanctum::actingAs($patient);
        $this->postJson("/api/v1/emergency-requests/{$emergencyId}/cancel", [
            'reason' => 'Pakai kendaraan lain',
        ])->assertConflict();

        // Driver starts to hospital
        $this->actingAsDriver($driver);
        $startToHospResp = $this->postJson("/api/v1/driver/requests/{$emergencyId}/start-to-hospital");
        $startToHospResp->assertOk()->assertJsonPath('data.status', 'en_route_to_hospital');

        // Driver completes trip at hospital
        $completeResp = $this->postJson("/api/v1/driver/requests/{$emergencyId}/complete");
        $completeResp->assertOk()->assertJsonPath('data.status', 'completed');
        $this->assertSame(DriverAvailability::Online, $driver->fresh()->availability_status);

        // Task 3: Patient checks Riwayat -> request is marked completed
        Sanctum::actingAs($patient);
        $riwayatResp = $this->getJson('/api/v1/emergency-requests');
        $riwayatResp->assertOk()
            ->assertJsonPath('data.0.id', $emergencyId)
            ->assertJsonPath('data.0.status', 'completed')
            ->assertJsonPath('data.0.driver.name', 'Hendra Supir')
            ->assertJsonPath('data.0.hospital.name', 'RS Cipto Mangunkusumo');
    }

    public function test_executes_radius_search_exhaustion_at_25_km_and_retry_search_flow(): void
    {
        $patient = User::factory()->withRole('user')->create();
        Sanctum::actingAs($patient);

        // Online driver is 20 km away
        $driver = DriverProfile::factory()->onlineAt(-6.4000, 106.8125)->create();

        // 1. Create request at pickup (-6.2245, 106.8125)
        $storeResp = $this->postJson('/api/v1/emergency-requests', [
            'emergency_type' => 'Sesak Nafas',
            'severity_level' => 'high',
            'is_for_self' => true,
            'patient_notes' => 'Testing radius steps',
            'pickup_latitude' => -6.2245,
            'pickup_longitude' => 106.8125,
            'pickup_address' => 'Jl. HR Rasuna Said',
        ]);
        $emergencyId = $storeResp->json('data.id');

        // Initial radius: 3 km (driver is 20 km away, so no offer)
        $this->getJson("/api/v1/emergency-requests/{$emergencyId}")
            ->assertJsonPath('data.search_radius_km', 3);
        $this->assertSame(0, DispatchLog::where('emergency_request_id', $emergencyId)->count());

        // Manually expand radius: 3 -> 5
        $this->postJson("/api/v1/emergency-requests/{$emergencyId}/radius")
            ->assertJsonPath('data.search_radius_km', 5);

        // Manually expand radius: 5 -> 10
        $this->postJson("/api/v1/emergency-requests/{$emergencyId}/radius")
            ->assertJsonPath('data.search_radius_km', 10);

        // Manually expand radius: 10 -> 15
        $this->postJson("/api/v1/emergency-requests/{$emergencyId}/radius")
            ->assertJsonPath('data.search_radius_km', 15);

        // Still no offer at 15 km
        $this->assertSame(0, DispatchLog::where('emergency_request_id', $emergencyId)->count());

        // Manually expand radius: 15 -> 25
        $expandTo25 = $this->postJson("/api/v1/emergency-requests/{$emergencyId}/radius");
        $expandTo25->assertJsonPath('data.search_radius_km', 25);

        // Now driver at 20 km is covered and receives offer!
        $this->assertTrue(DispatchLog::where('emergency_request_id', $emergencyId)->where('driver_id', $driver->id)->exists());

        // Driver declines the offer
        $this->actingAsDriver($driver);
        $this->postJson("/api/v1/driver/requests/{$emergencyId}/decline")->assertNoContent();

        // After decline, with nobody left within 25 km, search is exhausted
        Sanctum::actingAs($patient);
        $exhaustedResp = $this->getJson("/api/v1/emergency-requests/{$emergencyId}");
        $exhaustedResp->assertOk()
            ->assertJsonPath('data.search_radius_km', 25)
            ->assertJsonPath('data.search_exhausted', true);

        // Patient clicks "Cari Lagi" (POST /emergency-requests/{id}/retry)
        $retryResp = $this->postJson("/api/v1/emergency-requests/{$emergencyId}/retry");
        $retryResp->assertOk()
            ->assertJsonPath('data.search_radius_km', 25)
            ->assertJsonPath('data.search_exhausted', false);

        // The declined driver receives the offer again!
        $this->assertSame('offered', DispatchLog::where('emergency_request_id', $emergencyId)->where('driver_id', $driver->id)->latest('offered_at')->first()->status);
    }

    public function test_handles_mobile_edge_cases_string_comma_allergies_put_auth_me_alias_and_cancel_reason_field(): void
    {
        $patient = User::factory()->withRole('user')->create([
            'full_name' => 'Original Name',
            'phone' => '08123456789',
        ]);
        Sanctum::actingAs($patient);

        // Test PUT /api/v1/auth/me alias
        $this->putJson('/api/v1/auth/me', [
            'full_name' => 'Updated via Auth Me',
            'phone' => '0899999999',
        ])->assertOk()
            ->assertJsonPath('data.full_name', 'Updated via Auth Me');

        // Test PUT /me/medical-profile with comma-separated strings instead of JSON array
        $this->putJson('/api/v1/me/medical-profile', [
            'blood_type' => 'B-',
            'allergies' => 'Penisilin, Debu, Telur',
            'medical_conditions' => 'Hipertensi, Asma',
            'age' => 30, // infer birth_date
        ])->assertOk()
            ->assertJsonPath('data.blood_type', 'B-')
            ->assertJsonPath('data.allergies', ['Penisilin', 'Debu', 'Telur'])
            ->assertJsonPath('data.medical_conditions', ['Hipertensi', 'Asma']);

        $this->assertNotNull($patient->fresh()->medicalProfile->birth_date);

        // Test cancel using 'cancel_reason' parameter instead of 'reason'
        $emergency = EmergencyRequest::factory()->for($patient)->create([
            'status' => EmergencyStatus::SearchingDriver->value,
        ]);

        $this->postJson("/api/v1/emergency-requests/{$emergency->id}/cancel", [
            'cancel_reason' => 'Salah lokasi penjemputan',
        ])->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancel_reason', 'Salah lokasi penjemputan');

        $this->assertSame('Salah lokasi penjemputan', $emergency->fresh()->cancel_reason);
    }
}
