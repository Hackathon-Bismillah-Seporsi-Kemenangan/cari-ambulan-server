<?php

namespace Tests\Feature;

use App\Enums\DriverAvailability;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverAvailabilityApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(UserSeeder::class);
    }

    public function test_driver_can_view_profile(): void
    {
        $loginRes = $this->postJson('/api/v1/auth/login', [
            'email' => 'driver@cariambulan.id',
            'password' => 'Driver@123',
            'device_name' => 'test-device',
        ]);

        $token = $loginRes->json('token');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/driver/profile');

        $response->assertStatus(200)
            ->assertJsonPath('data.license_number', 'SIM-31750001')
            ->assertJsonPath('data.vehicle.plate_number', 'B 1199 SGM')
            ->assertJsonPath('data.hospital.name', 'RS Medika');
    }

    public function test_driver_can_toggle_online_and_offline(): void
    {
        $loginRes = $this->postJson('/api/v1/auth/login', [
            'email' => 'driver@cariambulan.id',
            'password' => 'Driver@123',
            'device_name' => 'test-device',
        ]);
        $token = $loginRes->json('token');

        // 1. Switch online with coordinates
        $onlineRes = $this->withHeader('Authorization', "Bearer $token")
            ->putJson('/api/v1/driver/availability', [
                'status' => 'online',
                'latitude' => -6.2088,
                'longitude' => 106.8222,
            ]);

        $onlineRes->assertStatus(200)
            ->assertJsonPath('data.availability_status', 'online')
            ->assertJsonPath('data.location.latitude', -6.2088)
            ->assertJsonPath('data.location.longitude', 106.8222);

        // 2. Switch back offline
        $offlineRes = $this->withHeader('Authorization', "Bearer $token")
            ->putJson('/api/v1/driver/availability', [
                'status' => 'offline',
            ]);

        $offlineRes->assertStatus(200)
            ->assertJsonPath('data.availability_status', 'offline');
    }

    public function test_online_fails_without_coordinates(): void
    {
        $loginRes = $this->postJson('/api/v1/auth/login', [
            'email' => 'driver@cariambulan.id',
            'password' => 'Driver@123',
            'device_name' => 'test-device',
        ]);
        $token = $loginRes->json('token');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->putJson('/api/v1/driver/availability', [
                'status' => 'online',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    public function test_patient_cannot_access_driver_endpoints(): void
    {
        $loginRes = $this->postJson('/api/v1/auth/login', [
            'email' => 'pasien@cariambulan.id',
            'password' => 'Pasien@123',
            'device_name' => 'test-device',
        ]);
        $token = $loginRes->json('token');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/driver/profile');

        $response->assertStatus(403);
    }
}

