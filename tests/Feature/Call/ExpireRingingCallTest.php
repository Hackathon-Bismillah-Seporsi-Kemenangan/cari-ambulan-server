<?php

namespace Tests\Feature\Call;

use App\Enums\Call\CallEndReason;
use App\Enums\Call\CallStatus;
use App\Events\Call\CallMissed;
use App\Jobs\Call\ExpireRingingCall;
use App\Models\Call;
use App\Services\Call\LiveKitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

class ExpireRingingCallTest extends TestCase
{
    use RefreshDatabase;

    public function test_expires_a_ringing_call_and_marks_it_missed_with_timeout_reason(): void
    {
        Event::fake([CallMissed::class]);

        $liveKitMock = Mockery::mock(LiveKitService::class);
        $liveKitMock->shouldReceive('deleteRoom')->once()->with('test-expire-room')->andReturn(true);
        app()->instance(LiveKitService::class, $liveKitMock);

        $call = Call::factory()->ringing()->create([
            'room_name' => 'test-expire-room',
            'started_at' => now()->subSeconds(35),
        ]);

        $job = new ExpireRingingCall((string) $call->id);
        $job->handle($liveKitMock);

        $call->refresh();
        $this->assertEquals(CallStatus::Missed, $call->status);
        $this->assertEquals(CallEndReason::Timeout, $call->end_reason);
        $this->assertNotNull($call->ended_at);

        Event::assertDispatched(CallMissed::class);
    }

    public function test_does_not_expire_a_call_that_has_already_been_accepted(): void
    {
        Event::fake([CallMissed::class]);

        $liveKitMock = Mockery::mock(LiveKitService::class);
        $liveKitMock->shouldNotReceive('deleteRoom');

        $call = Call::factory()->accepted()->create([
            'room_name' => 'test-accepted-room',
        ]);

        $job = new ExpireRingingCall((string) $call->id);
        $job->handle($liveKitMock);

        $call->refresh();
        $this->assertEquals(CallStatus::Accepted, $call->status);
        Event::assertNotDispatched(CallMissed::class);
    }
}
