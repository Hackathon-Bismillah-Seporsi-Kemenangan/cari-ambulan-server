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

class DispatchEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(UserSeeder::class);
    }

    public function test_can_offer_request_to_nearest_online_driver(): void
    {
        /** @var User $patient */
        $patient = User::where('email', 'pasien@cariambulan.id')->firstOrFail();

        /** @var DriverProfile $driver */
        $driver = DriverProfile::firstOrFail();
        $driver->update([
            'availability_status' => DriverAvailability::Online->value,
            'current_latitude' => -6.2088,
            'current_longitude' => 106.8222,
            'location_updated_at' => now(),
        ]);

        /** @var EmergencyRequest $request */
        $request = EmergencyRequest::create([
            'user_id' => $patient->id,
            'request_code' => EmergencyRequest::generateRequestCode(),
            'is_for_self' => true,
            'emergency_type' => 'cardiac',
            'severity_level' => 'critical',
            'patient_notes' => 'Nyeri dada hebat',
            'pickup_latitude' => -6.2100,
            'pickup_longitude' => 106.8200,
            'pickup_address' => 'Jl. Sudirman No. 1, Jakarta',
            'status' => EmergencyStatus::SearchingDriver->value,
            'search_radius_km' => 3,
            'request_at' => now(),
        ]);

        $log = $request->offerToNextDriver();

        $this->assertNotNull($log);
        $this->assertInstanceOf(DispatchLog::class, $log);
        $this->assertEquals($driver->id, $log->driver_id);
        $this->assertEquals(DispatchStatus::Offered->value, $log->status);
        $this->assertLessThan(1.0, $log->distance_km);
        $this->assertGreaterThanOrEqual(28, $log->secondsRemaining());
        $this->assertLessThanOrEqual(30, $log->secondsRemaining());
    }

    public function test_will_not_offer_to_offline_driver(): void
    {
        /** @var User $patient */
        $patient = User::where('email', 'pasien@cariambulan.id')->firstOrFail();

        /** @var DriverProfile $driver */
        $driver = DriverProfile::firstOrFail();
        $driver->update([
            'availability_status' => DriverAvailability::Offline->value,
            'current_latitude' => -6.2088,
            'current_longitude' => 106.8222,
            'location_updated_at' => now(),
        ]);

        $request = EmergencyRequest::create([
            'user_id' => $patient->id,
            'request_code' => EmergencyRequest::generateRequestCode(),
            'pickup_latitude' => -6.2100,
            'pickup_longitude' => 106.8200,
            'pickup_address' => 'Jl. Sudirman No. 1, Jakarta',
            'status' => EmergencyStatus::SearchingDriver->value,
            'search_radius_km' => 3,
            'request_at' => now(),
        ]);

        $log = $request->offerToNextDriver();

        $this->assertNull($log);
        $this->assertDatabaseCount('dispatch_logs', 0);
    }

    public function test_widen_radius_expands_step_by_step(): void
    {
        /** @var User $patient */
        $patient = User::where('email', 'pasien@cariambulan.id')->firstOrFail();

        $request = EmergencyRequest::create([
            'user_id' => $patient->id,
            'request_code' => EmergencyRequest::generateRequestCode(),
            'pickup_latitude' => -6.2100,
            'pickup_longitude' => 106.8200,
            'pickup_address' => 'Jl. Sudirman No. 1, Jakarta',
            'status' => EmergencyStatus::SearchingDriver->value,
            'search_radius_km' => 3,
            'request_at' => now(),
        ]);

        $this->assertTrue($request->widenRadius());
        $this->assertEquals(5, $request->fresh()->search_radius_km);

        $this->assertTrue($request->widenRadius());
        $this->assertEquals(10, $request->fresh()->search_radius_km);

        $this->assertTrue($request->widenRadius());
        $this->assertEquals(15, $request->fresh()->search_radius_km);

        $this->assertTrue($request->widenRadius());
        $this->assertEquals(25, $request->fresh()->search_radius_km);

        $this->assertFalse($request->widenRadius());
        $this->assertEquals(25, $request->fresh()->search_radius_km);
    }

    public function test_redispatch_waiting_times_out_expired_offers(): void
    {
        /** @var User $patient */
        $patient = User::where('email', 'pasien@cariambulan.id')->firstOrFail();
        /** @var DriverProfile $driver */
        $driver = DriverProfile::firstOrFail();

        $request = EmergencyRequest::create([
            'user_id' => $patient->id,
            'request_code' => EmergencyRequest::generateRequestCode(),
            'pickup_latitude' => -6.2100,
            'pickup_longitude' => 106.8200,
            'pickup_address' => 'Jl. Sudirman No. 1, Jakarta',
            'status' => EmergencyStatus::SearchingDriver->value,
            'search_radius_km' => 3,
            'request_at' => now()->subMinutes(2),
        ]);

        $log = DispatchLog::create([
            'emergency_request_id' => $request->id,
            'driver_id' => $driver->id,
            'status' => DispatchStatus::Offered->value,
            'distance_km' => 0.5,
            'offered_at' => now()->subSeconds(35),
        ]);

        EmergencyRequest::redispatchWaiting();

        $this->assertEquals(DispatchStatus::Timeout->value, $log->fresh()->status);
        $this->assertNotNull($log->fresh()->responded_at);
    }
}

