<?php

namespace App\Policies;

use App\Models\Call;
use App\Models\User;

class CallPolicy
{
    /**
     * Determine whether the user can view the call details.
     */
    public function view(User $user, Call $call): bool
    {
        return $call->isParticipant($user);
    }

    /**
     * Determine whether the user can accept the call.
     * Only the receiver can accept an incoming call.
     */
    public function accept(User $user, Call $call): bool
    {
        return $call->isReceiver($user);
    }

    /**
     * Determine whether the user can reject the call.
     * Only the receiver can reject an incoming call.
     */
    public function reject(User $user, Call $call): bool
    {
        return $call->isReceiver($user);
    }

    /**
     * Determine whether the user can cancel the call.
     * Only the caller can cancel a ringing call.
     */
    public function cancel(User $user, Call $call): bool
    {
        return $call->isCaller($user);
    }

    /**
     * Determine whether the user can end the call.
     * Either participant (caller or receiver) can end the call.
     */
    public function end(User $user, Call $call): bool
    {
        return $call->isParticipant($user);
    }

    /**
     * Determine whether the user can retrieve the LiveKit room token.
     * Only active participants of this specific call can fetch a room token.
     */
    public function generateToken(User $user, Call $call): bool
    {
        return $call->isParticipant($user);
    }
}
