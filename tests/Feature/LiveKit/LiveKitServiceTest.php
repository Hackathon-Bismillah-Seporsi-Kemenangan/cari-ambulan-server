<?php

namespace Tests\Feature\LiveKit;

use App\Services\Call\LiveKitService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LiveKitServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'livekit.url' => 'https://livekit.example.com',
            'livekit.api_key' => 'api_key_1234567890',
            'livekit.api_secret' => 'supersecretkey_must_be_32_characters_long_min',
        ]);
    }

    public function test_creates_an_audio_room_with_maximum_participants_set_to_2(): void
    {
        Http::fake([
            'https://livekit.example.com/twirp/livekit.RoomService/CreateRoom' => Http::response([
                'sid' => 'RM_12345',
                'name' => 'test-room-uuid',
                'max_participants' => 2,
            ], 200),
        ]);

        $service = app(LiveKitService::class);
        $result = $service->createRoom('test-room-uuid');

        $this->assertNotNull($result);
        $this->assertEquals('test-room-uuid', $result['name']);
        $this->assertEquals(2, $result['max_participants']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://livekit.example.com/twirp/livekit.RoomService/CreateRoom'
                && $request['name'] === 'test-room-uuid'
                && $request['max_participants'] === 2;
        });
    }

    public function test_handles_livekit_api_failures_gracefully(): void
    {
        Http::fake([
            'https://livekit.example.com/twirp/livekit.RoomService/CreateRoom' => Http::response([
                'error' => 'internal server error',
            ], 500),
        ]);

        $service = app(LiveKitService::class);
        $result = $service->createRoom('failing-room');

        $this->assertNull($result);
    }

    public function test_deletes_a_room_via_livekit_room_service(): void
    {
        Http::fake([
            'https://livekit.example.com/twirp/livekit.RoomService/DeleteRoom' => Http::response([], 200),
        ]);

        $service = app(LiveKitService::class);
        $deleted = $service->deleteRoom('room-to-delete');

        $this->assertTrue($deleted);
    }
}
