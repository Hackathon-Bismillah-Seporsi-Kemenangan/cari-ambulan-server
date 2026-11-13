<?php

use App\Models\Call;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (string) $user->id === (string) $id;
});

Broadcast::channel('users.{id}', function (User $user, string $id): bool {
    return (string) $user->id === (string) $id;
});

Broadcast::channel('users.{userId}.calls', function (User $user, string $userId): bool {
    return (string) $user->id === (string) $userId;
});

Broadcast::channel('calls.{id}', function (User $user, string $id): bool {
    $call = Call::find($id);

    return $call !== null && $call->isParticipant($user);
});
