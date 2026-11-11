<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AmbulanceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_nearest_available_ambulance_with_drive_time(): void
    {
        Sanctum::actingAs(User::factory()->withRole('user')->create());
        DriverProfile::factory()->onlineAt(-6.2300, 106.8200)->create();
        DriverProfile::factory()->onlineAt(-6.1500, 106.9000)->create();

        $this->getJson('/api/v1/ambulances/nearest?latitude=-6.2245&longitude=106.8125')
            ->assertOk()
            ->assertJsonPath('data.latitude', -6.23)
            ->assertJsonPath('data.longitude', 106.82)
            ->assertJsonPath('data.distance_km', 1.03)
            ->assertJsonPath('data.eta_minutes', 3);
    }

    public function test_returns_null_when_no_ambulance_is_available(): void
    {
        Sanctum::actingAs(User::factory()->withRole('user')->create());
        DriverProfile::factory()->withAmbulance()->create();

        $this->getJson('/api/v1/ambulances/nearest?latitude=-6.2245&longitude=106.8125')
            ->assertOk()
            ->assertExactJson(['data' => null]);
    }

    public function test_requires_coordinates_for_nearest_ambulance(): void
    {
        Sanctum::actingAs(User::factory()->withRole('user')->create());

        $this->getJson('/api/v1/ambulances/nearest')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    public function test_nearest_ambulance_endpoint_is_restricted_to_user_role(): void
    {
        Sanctum::actingAs(User::factory()->withRole('driver')->create());

        $this->getJson('/api/v1/ambulances/nearest?latitude=-6.2245&longitude=106.8125')
            ->assertForbidden();
    }
}

