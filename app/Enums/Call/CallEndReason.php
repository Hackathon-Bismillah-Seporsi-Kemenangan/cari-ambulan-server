<?php

namespace App\Enums\Call;

enum CallEndReason: string
{
    case CallerEnded = 'caller_ended';
    case ReceiverEnded = 'receiver_ended';
    case Rejected = 'rejected';
    case Timeout = 'timeout';
    case ConnectionLost = 'connection_lost';
    case SystemError = 'system_error';
}
