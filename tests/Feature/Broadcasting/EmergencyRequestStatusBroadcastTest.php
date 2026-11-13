<?php

namespace Tests\Feature\Broadcasting;

use App\Enums\EmergencyStatus;
use App\Events\EmergencyRequestStatusUpdated;
use App\Models\EmergencyRequest;
use App\Models\Hospital;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class EmergencyRequestStatusBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_broadcasts_emergency_request_status_updated_when_emergency_request_status_changes(): void
    {
        Event::fake([EmergencyRequestStatusUpdated::class]);

        $hospital = Hospital::factory()->create(['name' => 'RS Medika']);

        $request = EmergencyRequest::factory()->create([
            'hospital_id' => $hospital->id,
            'status' => EmergencyStatus::SearchingDriver->value,
            'emergency_type' => 'Sesak Nafas',
        ]);

        // Updating status should trigger the broadcast
        $request->update(['status' => EmergencyStatus::DriverAssigned->value]);

        Event::assertDispatched(EmergencyRequestStatusUpdated::class, function ($event) use ($hospital) {
            return $event->emergencyRequest->status === EmergencyStatus::DriverAssigned->value
                && $event->emergencyRequest->hospital_id === $hospital->id
                && in_array('hospital.'.$hospital->id, array_map(fn ($c) => $c->name, $event->broadcastOn()))
                && in_array('emergency-requests', array_map(fn ($c) => $c->name, $event->broadcastOn()));
        });
    }

    public function test_includes_igd_preparation_and_hospital_id_in_broadcast_payload(): void
    {
        $hospital = Hospital::factory()->create(['name' => 'RSUD Tarakan']);

        $request = EmergencyRequest::factory()->create([
            'hospital_id' => $hospital->id,
            'status' => EmergencyStatus::SearchingDriver->value,
            'emergency_type' => 'Kecelakaan',
        ]);

        $event = new EmergencyRequestStatusUpdated($request);
        $payload = $event->broadcastWith();

        $this->assertArrayHasKey('id', $payload);
        $this->assertSame($request->id, $payload['id']);
        $this->assertSame($request->request_code, $payload['request_code']);
        $this->assertSame(EmergencyStatus::SearchingDriver->value, $payload['status']);
        $this->assertSame($hospital->id, $payload['hospital_id']);
        $this->assertSame('Kecelakaan', $payload['emergency_type']);
        $this->assertSame('IGD menyiapkan tim trauma.', $payload['igd_preparation']);
    }

    public function test_does_not_broadcast_when_status_is_unchanged(): void
    {
        Event::fake([EmergencyRequestStatusUpdated::class]);

        $request = EmergencyRequest::factory()->create([
            'status' => EmergencyStatus::DriverAssigned->value,
        ]);

        Event::fake([EmergencyRequestStatusUpdated::class]);

        // Updating a field other than status should not dispatch status updated event
        $request->update(['pickup_address' => 'Jl. Kebon Sirih No. 99']);

        Event::assertNotDispatched(EmergencyRequestStatusUpdated::class);
    }
}
