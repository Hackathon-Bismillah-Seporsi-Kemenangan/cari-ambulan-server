<?php

namespace App\Jobs\Call;

use App\Services\Call\LiveKitService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CleanupCallRoom implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $roomName) {}

    public function handle(LiveKitService $liveKit): void
    {
        $liveKit->deleteRoom($this->roomName);
    }
}
