<?php

namespace Tests\Feature\Console;

use App\Models\DriverProfile;
use App\Models\EmergencyRequest;
use App\Models\Hospital;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignAmbulanceCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(UserSeeder::class);
    }

    public function test_links_ambulance_so_driver_can_be_dispatched(): void
    {
        $driver = DriverProfile::factory()->create([
            'availability_status' => 'online',
            'current_latitude' => -6.2088,
            'current_longitude' => 106.8222,
            'location_updated_at' => now(),
        ]);

        $this->artisan('driver:assign-ambulance', [
            'email' => $driver->user->email,
            'plate' => 'B 1234 XYZ',
        ])->assertSuccessful();

        $ambulance = $driver->ambulance()->first();
        $this->assertNotNull($ambulance);
        $this->assertEquals('B 1234 XYZ', $ambulance->plate_number);
        $this->assertTrue($ambulance->is_active);

        $emergency = EmergencyRequest::factory()->create([
            'pickup_latitude' => -6.2088,
            'pickup_longitude' => 106.8222,
        ]);
        $offer = $emergency->offerToNextDriver();
        $this->assertNotNull($offer);
        $this->assertEquals($driver->id, $offer->driver_id);
    }

    public function test_updates_existing_ambulance_plate(): void
    {
        $driver = DriverProfile::factory()->withAmbulance()->create();

        $this->artisan('driver:assign-ambulance', [
            'email' => $driver->user->email,
            'plate' => 'B 9999 NEW',
        ])->assertSuccessful();

        $this->assertEquals(1, $driver->ambulance()->count());
        $this->assertEquals('B 9999 NEW', $driver->ambulance()->first()->plate_number);
    }

    public function test_rejects_unknown_driver_and_duplicate_plate(): void
    {
        $owner = DriverProfile::factory()->withAmbulance()->create();
        $driver = DriverProfile::factory()->create();

        $this->artisan('driver:assign-ambulance', [
            'email' => 'unknown@cariambulan.id',
            'plate' => 'B 1111 AAA',
        ])->assertFailed();

        $this->artisan('driver:assign-ambulance', [
            'email' => $driver->user->email,
            'plate' => $owner->ambulance->plate_number,
        ])->assertFailed();
    }

    public function test_links_hospital_when_driver_has_none(): void
    {
        $driver = DriverProfile::factory()->create(['hospital_id' => null]);
        $hospital = Hospital::factory()->create(['name' => 'RSUD Tarakan Jakarta']);

        // Without hospital option fails
        $this->artisan('driver:assign-ambulance', [
            'email' => $driver->user->email,
            'plate' => 'B 7777 HOS',
        ])->assertFailed();

        // With hospital option succeeds
        $this->artisan('driver:assign-ambulance', [
            'email' => $driver->user->email,
            'plate' => 'B 7777 HOS',
            '--hospital' => 'tarakan',
        ])->assertSuccessful();

        $this->assertEquals($hospital->id, $driver->fresh()->hospital_id);
        $this->assertEquals($hospital->id, $driver->ambulance()->first()->hospital_id);
    }
}
