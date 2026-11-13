<?php

namespace Tests\Feature;

use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(UserSeeder::class);
    }

    public function test_driver_can_login_and_receive_token(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'driver@cariambulan.id',
            'password' => 'Driver@123',
            'device_name' => 'flutter-test-device',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'token',
                'user' => [
                    'id',
                    'full_name',
                    'email',
                    'phone',
                    'roles',
                ],
            ]);

        $this->assertContains('driver', $response->json('user.roles'));
    }

    public function test_patient_can_login_and_receive_token(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'pasien@cariambulan.id',
            'password' => 'Pasien@123',
            'device_name' => 'flutter-test-device',
        ]);

        $response->assertStatus(200);
        $this->assertContains('user', $response->json('user.roles'));
    }

    public function test_authenticated_user_can_access_me_and_logout(): void
    {
        $loginRes = $this->postJson('/api/v1/auth/login', [
            'email' => 'pasien@cariambulan.id',
            'password' => 'Pasien@123',
            'device_name' => 'flutter-test-device',
        ]);

        $token = $loginRes->json('token');

        // Test GET /auth/me
        $meRes = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/auth/me');

        $meRes->assertStatus(200)
            ->assertJsonPath('data.email', 'pasien@cariambulan.id');

        // Test POST /auth/logout
        $logoutRes = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/auth/logout');

        $logoutRes->assertStatus(204);
    }

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'full_name' => 'Testing Baru',
            'email' => 'baru@cariambulan.id',
            'phone' => '08999999999',
            'alamat' => 'Jakarta Selatan',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
            'role' => 'user',
            'device_name' => 'flutter-device',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'token',
                'user' => ['id', 'full_name', 'email', 'roles'],
            ]);
    }
}
