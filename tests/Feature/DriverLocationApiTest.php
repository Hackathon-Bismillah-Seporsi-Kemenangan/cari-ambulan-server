<?php

namespace Tests\Feature;

use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverLocationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(UserSeeder::class);
    }

    public function test_driver_can_update_gps_location(): void
    {
        $loginRes = $this->postJson('/api/v1/auth/login', [
            'email' => 'driver@cariambulan.id',
            'password' => 'Driver@123',
            'device_name' => 'test-device',
        ]);
        $token = $loginRes->json('token');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/driver/location', [
                'latitude' => -6.1754,
                'longitude' => 106.8272,
            ]);

        $response->assertStatus(204);

        // Verify profile updated in database
        $this->assertDatabaseHas('driver_profiles', [
            'current_latitude' => -6.1754,
            'current_longitude' => 106.8272,
        ]);
    }

    public function test_location_update_fails_on_invalid_coordinates(): void
    {
        $loginRes = $this->postJson('/api/v1/auth/login', [
            'email' => 'driver@cariambulan.id',
            'password' => 'Driver@123',
            'device_name' => 'test-device',
        ]);
        $token = $loginRes->json('token');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/driver/location', [
                'latitude' => 120.0, // Invalid: exceeds 90
                'longitude' => 106.8272,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['latitude']);
    }

    public function test_patient_cannot_post_driver_location(): void
    {
        $loginRes = $this->postJson('/api/v1/auth/login', [
            'email' => 'pasien@cariambulan.id',
            'password' => 'Pasien@123',
            'device_name' => 'test-device',
        ]);
        $token = $loginRes->json('token');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/driver/location', [
                'latitude' => -6.1754,
                'longitude' => 106.8272,
            ]);

        $response->assertStatus(403);
    }
}

