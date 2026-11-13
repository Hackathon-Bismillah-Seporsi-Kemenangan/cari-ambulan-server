<?php

namespace App\Events;

use App\Models\EmergencyRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmergencyRequestStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public EmergencyRequest $emergencyRequest) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new Channel('emergency-requests'),
        ];

        if ($this->emergencyRequest->hospital_id) {
            $channels[] = new Channel('hospital.'.$this->emergencyRequest->hospital_id);
        }

        return $channels;
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'EmergencyRequestStatusUpdated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $this->emergencyRequest->loadMissing(['user', 'driverProfile.user', 'ambulance', 'hospital']);

        return [
            'id' => $this->emergencyRequest->id,
            'request_code' => $this->emergencyRequest->request_code,
            'status' => $this->emergencyRequest->status,
            'hospital_id' => $this->emergencyRequest->hospital_id,
            'emergency_type' => $this->emergencyRequest->emergency_type,
            'severity_level' => $this->emergencyRequest->severity_level,
            'patient_name' => $this->emergencyRequest->user?->full_name ?? 'Pasien Darurat',
            'patient_phone' => $this->emergencyRequest->user?->phone,
            'patient_notes' => $this->emergencyRequest->patient_notes,
            'igd_preparation' => $this->emergencyRequest->igdPreparation(),
            'driver_name' => $this->emergencyRequest->driverProfile?->user?->full_name,
            'ambulance_plate' => $this->emergencyRequest->ambulance?->plate_number,
            'updated_at' => $this->emergencyRequest->updated_at?->diffForHumans() ?? 'Baru saja',
        ];
    }
}
