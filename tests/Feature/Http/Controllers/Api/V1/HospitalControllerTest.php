<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Hospital;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HospitalControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_nearest_hospital_with_distance(): void
    {
        Sanctum::actingAs(User::factory()->withRole('user')->create());

        $near = Hospital::factory()->create([
            'name' => 'RS Medika Dekat',
            'latitude' => -6.2250,
            'longitude' => 106.8130,
        ]);
        Hospital::factory()->create([
            'name' => 'RS Jauh',
            'latitude' => -6.1000,
            'longitude' => 106.9500,
        ]);

        $response = $this->getJson('/api/v1/hospitals/nearest?latitude=-6.2245&longitude=106.8125');

        $response->assertOk()
            ->assertJsonPath('data.id', $near->id)
            ->assertJsonPath('data.name', 'RS Medika Dekat');

        $this->assertIsNumeric($response->json('data.distance_km'));
    }

    public function test_returns_null_when_no_hospital_has_coordinates(): void
    {
        Sanctum::actingAs(User::factory()->withRole('user')->create());
        Hospital::query()->update(['latitude' => null, 'longitude' => null]);
        Hospital::factory()->create(['latitude' => null, 'longitude' => null]);

        $this->getJson('/api/v1/hospitals/nearest?latitude=-6.2245&longitude=106.8125')
            ->assertOk()
            ->assertExactJson(['data' => null]);
    }

    public function test_requires_coordinates_for_nearest_hospital(): void
    {
        Sanctum::actingAs(User::factory()->withRole('user')->create());

        $this->getJson('/api/v1/hospitals/nearest')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    }
}
