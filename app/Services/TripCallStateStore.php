<?php

namespace App\Services;

use App\Enums\CallStatus;
use App\Models\TripCall;
use Illuminate\Contracts\Cache\Repository;

class TripCallStateStore
{
    protected Repository $cache;

    public function __construct(?Repository $cache = null)
    {
        $this->cache = $cache ?? cache()->store('trip-call');
    }

    public function sync(TripCall $call): void
    {
        if (in_array($call->status, $this->terminalStatuses(), true)) {
            $this->forget($call);

            return;
        }

        if ($call->status === CallStatus::Answered->value) {
            $this->store($call, (int) config('services.trip_call.max_seconds', 7200));

            return;
        }

        if ($call->isRinging()) {
            $ttl = $call->rang_at->copy()->addSeconds(TripCall::RING_SECONDS)->getTimestamp() - now()->getTimestamp();
            $this->store($call, max(1, $ttl));

            return;
        }

        $this->forget($call);
    }

    public function forget(TripCall $call): void
    {
        $this->cache->forget($this->key($call->id));

        foreach ($this->participantIds($call) as $userId) {
            $this->cache->forget($this->userKey($userId));
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForUser(string $userId): ?array
    {
        $id = $this->cache->get($this->userKey($userId));

        if ($id === null) {
            return null;
        }

        $snapshot = $this->cache->get($this->key($id));

        if ($snapshot === null) {
            $this->cache->forget($this->userKey($userId));

            return null;
        }

        return $snapshot;
    }

    protected function store(TripCall $call, int $ttl): void
    {
        $snapshot = [
            'id' => (string) $call->id,
            'status' => $call->status,
            'caller_id' => (string) $call->caller_id,
        ];

        $this->cache->put($this->key($call->id), $snapshot, $ttl);

        foreach ($this->participantIds($call) as $userId) {
            $this->cache->put($this->userKey($userId), (string) $call->id, $ttl);
        }
    }

    /**
     * @return list<string>
     */
    protected function participantIds(TripCall $call): array
    {
        $call->loadMissing('emergencyRequest.driverProfile');
        $emergency = $call->emergencyRequest;

        return array_values(array_unique(array_filter([
            (string) $call->caller_id,
            (string) $emergency?->user_id,
            (string) $emergency?->driverProfile?->user_id,
        ])));
    }

    /**
     * @return list<string>
     */
    protected function terminalStatuses(): array
    {
        return [
            CallStatus::Ended->value,
            CallStatus::Declined->value,
            CallStatus::Missed->value,
            CallStatus::Failed->value,
        ];
    }

    protected function key(string $id): string
    {
        return 'trip-call:'.$id;
    }

    protected function userKey(string $userId): string
    {
        return 'trip-call:user:'.$userId;
    }
}

