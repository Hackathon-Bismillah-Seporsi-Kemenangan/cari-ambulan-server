<?php

namespace Tests\Feature;

use App\Enums\DriverAvailability;
use App\Models\Ambulance;
use App\Models\Concerns\CalculatesDistance;
use App\Models\DriverProfile;
use App\Models\Hospital;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_and_hospital_schema_and_relations(): void
    {
        $user = User::create([
            'full_name' => 'Driver Uji',
            'email' => 'driver.uji@cariambulan.id',
            'phone' => '08123456789',
            'alamat' => 'Jakarta Pusat',
            'password' => 'Driver@123',
        ]);

        $hospital = Hospital::create([
            'name' => 'RS Uji Medika',
            'address' => 'Jl. Uji No. 1',
            'emergency_phone' => '021119',
            'latitude' => -6.2005,
            'longitude' => 106.8160,
            'ambulance_entrance' => 'Gerbang Barat',
        ]);

        $driverProfile = DriverProfile::create([
            'user_id' => $user->id,
            'license_number' => 'SIM-TEST-123',
            'availability_status' => DriverAvailability::Online,
            'current_latitude' => -6.2088,
            'current_longitude' => 106.8222,
            'hospital_id' => $hospital->id,
        ]);

        $ambulance = Ambulance::create([
            'plate_number' => 'B 1234 TEST',
            'vehicle_type' => 'Ambulans ALS',
            'is_active' => true,
            'driver_profile_id' => $driverProfile->id,
            'hospital_id' => $hospital->id,
        ]);

        // Assert relationships
        $this->assertEquals($driverProfile->id, $user->driverProfile->id);
        $this->assertEquals(DriverAvailability::Online, $driverProfile->availability_status);
        $this->assertEquals($hospital->id, $driverProfile->hospital->id);
        $this->assertEquals($ambulance->id, $driverProfile->ambulance->id);

        // Test Haversine distance calculation
        $distance = CalculatesDistance::distanceKm(-6.2005, 106.8160, -6.2088, 106.8222);
        $this->assertGreaterThan(0.5, $distance);
        $this->assertLessThan(2.0, $distance);
    }
}

