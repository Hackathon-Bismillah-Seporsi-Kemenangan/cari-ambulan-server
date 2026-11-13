<?php

namespace Tests\Feature\Console;

use App\Enums\DriverAvailability;
use App\Enums\EmergencyStatus;
use App\Models\DriverProfile;
use App\Models\EmergencyRequest;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoCallCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(UserSeeder::class);
    }

    public function test_demo_call_fails_when_driver_has_no_active_order(): void
    {
        $this->artisan('demo:call --driver=driver@cariambulan.id')
            ->expectsOutputToContain('Driver has no active order')
            ->assertExitCode(1);
    }

    public function test_demo_call_places_a_ringing_call_on_active_order(): void
    {
        $driver = DriverProfile::whereHas('user', fn ($q) => $q->where('email', 'driver@cariambulan.id'))->firstOrFail();
        $driver->update(['availability_status' => DriverAvailability::Busy->value]);

        $trip = EmergencyRequest::factory()->create([
            'status' => EmergencyStatus::EnRouteToPickup->value,
            'driver_profile_id' => $driver->id,
            'ambulance_id' => $driver->ambulance->id,
            'accepted_at' => now(),
        ]);

        $this->artisan('demo:call --driver=driver@cariambulan.id')
            ->expectsOutputToContain('is calling the driver')
            ->assertExitCode(0);

        $this->assertDatabaseHas('trip_calls', [
            'emergency_request_id' => $trip->id,
            'status' => 'ringing',
        ]);
    }
}

