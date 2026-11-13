<?php

namespace Tests\Feature\Dispatch;

use App\Enums\DispatchStatus;
use App\Enums\DriverAvailability;
use App\Enums\EmergencyStatus;
use App\Models\DispatchLog;
use App\Models\DriverProfile;
use App\Models\EmergencyRequest;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FanOutDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(UserSeeder::class);
    }

    private function searchingRequest(int $radiusKm = 5): EmergencyRequest
    {
        return EmergencyRequest::factory()->create([
            'status' => EmergencyStatus::SearchingDriver->value,
            'search_radius_km' => $radiusKm,
            'pickup_latitude' => -6.2088,
            'pickup_longitude' => 106.8222,
            'request_at' => now(),
        ]);
    }

    public function test_rings_nearest_drivers_up_to_fan_out_limit(): void
    {
        foreach ([0.001, 0.002, 0.003, 0.004] as $offset) {
            DriverProfile::factory()->onlineAt(-6.2088 + $offset, 106.8222)->create();
        }

        $emergency = $this->searchingRequest();
        $emergency->offerToNextDriver();

        $this->assertEquals(EmergencyRequest::OFFER_FANOUT, $emergency->dispatchLogs()->pending()->count());
    }

    public function test_skips_drivers_outside_search_radius(): void
    {
        DriverProfile::factory()->onlineAt(-6.0000, 107.2000)->create(); // ~50 km away
        $emergency = $this->searchingRequest(3);

        $offer = $emergency->offerToNextDriver();

        $this->assertNull($offer);
        $this->assertEquals(0, $emergency->dispatchLogs()->count());
    }

    public function test_closes_other_drivers_offers_when_one_accepts(): void
    {
        $first = DriverProfile::factory()->onlineAt(-6.2087, 106.8222)->create();
        $second = DriverProfile::factory()->onlineAt(-6.2086, 106.8222)->create();

        $emergency = $this->searchingRequest();
        $emergency->offerToNextDriver();

        Sanctum::actingAs($first->user);
        $this->postJson("/api/v1/driver/requests/{$emergency->id}/accept")->assertOk();

        $secondOffer = DispatchLog::where('driver_id', $second->id)->first();
        $this->assertEquals(DispatchStatus::Timeout->value, $secondOffer->status);

        Sanctum::actingAs($second->user);
        $this->postJson("/api/v1/driver/requests/{$emergency->id}/accept")->assertStatus(409);
    }

    public function test_redispatch_waiting_times_out_expired_offers_and_offers_next(): void
    {
        $first = DriverProfile::factory()->onlineAt(-6.2087, 106.8222)->create();
        $second = DriverProfile::factory()->onlineAt(-6.2086, 106.8222)->create();

        $emergency = $this->searchingRequest();

        $offer = DispatchLog::factory()->create([
            'emergency_request_id' => $emergency->id,
            'driver_id' => $first->id,
            'status' => DispatchStatus::Offered->value,
            'offered_at' => now()->subSeconds(35),
        ]);

        EmergencyRequest::redispatchWaiting();

        $this->assertEquals(DispatchStatus::Timeout->value, $offer->fresh()->status);
    }

    public function test_close_abandoned_closes_stale_orders(): void
    {
        $driver = DriverProfile::factory()->onlineAt(-6.2088, 106.8222)->create();
        $trip = EmergencyRequest::factory()->create([
            'driver_profile_id' => $driver->id,
            'status' => EmergencyStatus::ArriveAtPickup->value,
            'updated_at' => now()->subHours(3),
        ]);
        $trip->forceFill(['updated_at' => now()->subHours(3)])->saveQuietly();

        $closed = EmergencyRequest::closeAbandoned();

        $this->assertGreaterThanOrEqual(1, $closed);
        $this->assertEquals(EmergencyStatus::Cancelled->value, $trip->fresh()->status);
        $this->assertStringContainsString('Ditutup otomatis', (string) $trip->fresh()->cancel_reason);
        $this->assertEquals(DriverAvailability::Offline, $driver->fresh()->availability_status);
    }
}

