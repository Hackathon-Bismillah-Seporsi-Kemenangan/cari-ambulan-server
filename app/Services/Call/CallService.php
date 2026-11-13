<?php

namespace App\Services\Call;

use App\Models\Call;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CallService
{
    /**
     * Retrieve paginated call history for a user with optional filters.
     *
     * @param  array{status?: string|null, date_from?: string|null, date_to?: string|null}  $filters
     */
    public function getCallHistory(User $user, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Call::query()
            ->forUser($user)
            ->with(['caller', 'receiver'])
            ->latest('created_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        return $query->paginate($perPage);
    }

    /**
     * Get a specific call, ensuring user is an authorized participant.
     */
    public function getCallForUser(string $callId, User $user): Call
    {
        /** @var Call $call */
        $call = Call::with(['caller', 'receiver', 'participants'])->findOrFail($callId);

        if (! $call->isParticipant($user)) {
            abort(403, 'Anda bukan peserta panggilan ini.');
        }

        return $call;
    }
}
