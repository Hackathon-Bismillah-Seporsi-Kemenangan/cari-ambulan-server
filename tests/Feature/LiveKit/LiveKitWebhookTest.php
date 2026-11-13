<?php

namespace Tests\Feature\LiveKit;

use App\Enums\Call\CallStatus;
use App\Events\Call\CallEnded;
use App\Models\Call;
use App\Models\CallParticipant;
use App\Models\ProcessedWebhook;
use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class LiveKitWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'livekit.url' => 'wss://test.livekit.cloud',
            'livekit.api_key' => 'test_api_key_1234567890',
            'livekit.api_secret' => 'supersecretkey_must_be_32_characters_long_min',
        ]);
    }

    private function makeLiveKitToken(string $body): string
    {
        $secret = config('livekit.api_secret');
        $apiKey = config('livekit.api_key');

        return JWT::encode([
            'iss' => $apiKey,
            'sha256' => hash('sha256', $body),
            'exp' => time() + 60,
        ], $secret, 'HS256');
    }

    public function test_rejects_webhooks_with_invalid_signatures(): void
    {
        $response = $this->postJson('/api/v1/webhooks/livekit', ['event' => 'room_started'], [
            'Authorization' => 'Bearer invalid_signature_token',
        ]);

        $response->assertStatus(401);
    }

    public function test_processes_valid_webhook_and_records_it_in_processed_webhooks(): void
    {
        Call::factory()->create(['room_name' => 'some-room']);

        $payload = [
            'id' => 'evt_12345',
            'event' => 'room_started',
            'room' => ['name' => 'some-room'],
        ];
        $json = json_encode($payload);
        $token = $this->makeLiveKitToken($json);

        $response = $this->call('POST', '/api/v1/webhooks/livekit', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'CONTENT_TYPE' => 'application/json',
        ], $json);

        $response->assertOk()
            ->assertJson(['status' => 'handled', 'event' => 'room_started']);

        $this->assertTrue(ProcessedWebhook::where('event_id', 'evt_12345')->exists());
    }

    public function test_prevents_duplicate_event_processing_idempotently(): void
    {
        ProcessedWebhook::create([
            'event_id' => 'duplicate_evt_1',
            'event_type' => 'room_started',
            'processed_at' => now(),
        ]);

        $payload = [
            'id' => 'duplicate_evt_1',
            'event' => 'room_started',
            'room' => ['name' => 'room-xyz'],
        ];
        $json = json_encode($payload);
        $token = $this->makeLiveKitToken($json);

        $response = $this->call('POST', '/api/v1/webhooks/livekit', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'CONTENT_TYPE' => 'application/json',
        ], $json);

        $response->assertOk()
            ->assertJson(['status' => 'already_processed']);
    }

    public function test_updates_participant_joined_and_left_timestamps(): void
    {
        $caller = User::factory()->create();
        $receiver = User::factory()->create();
        $call = Call::factory()->ringing()->create([
            'caller_id' => $caller->id,
            'receiver_id' => $receiver->id,
            'room_name' => 'room-participants-test',
        ]);

        // 1. Participant joined
        $joinPayload = [
            'id' => 'evt_join_1',
            'event' => 'participant_joined',
            'room' => ['name' => 'room-participants-test'],
            'participant' => ['identity' => (string) $caller->id],
        ];
        $json = json_encode($joinPayload);
        $this->call('POST', '/api/v1/webhooks/livekit', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->makeLiveKitToken($json),
            'CONTENT_TYPE' => 'application/json',
        ], $json)->assertOk();

        $participant = CallParticipant::where('call_id', $call->id)->where('livekit_identity', (string) $caller->id)->first();
        $this->assertNotNull($participant);
        $this->assertNotNull($participant->joined_at);
        $this->assertNull($participant->left_at);

        // 2. Participant left
        $leavePayload = [
            'id' => 'evt_leave_1',
            'event' => 'participant_left',
            'room' => ['name' => 'room-participants-test'],
            'participant' => ['identity' => (string) $caller->id],
        ];
        $jsonLeave = json_encode($leavePayload);
        $this->call('POST', '/api/v1/webhooks/livekit', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->makeLiveKitToken($jsonLeave),
            'CONTENT_TYPE' => 'application/json',
        ], $jsonLeave)->assertOk();

        $this->assertNotNull($participant->fresh()->left_at);
    }

    public function test_ends_call_on_room_finished_and_broadcasts_call_ended(): void
    {
        Event::fake([CallEnded::class]);

        $caller = User::factory()->create();
        $receiver = User::factory()->create();
        $call = Call::factory()->accepted()->create([
            'caller_id' => $caller->id,
            'receiver_id' => $receiver->id,
            'room_name' => 'room-finish-test',
            'answered_at' => now()->subSeconds(50),
        ]);

        $payload = [
            'id' => 'evt_finished_1',
            'event' => 'room_finished',
            'room' => [
                'name' => 'room-finish-test',
                'duration' => 50,
            ],
        ];
        $json = json_encode($payload);
        $this->call('POST', '/api/v1/webhooks/livekit', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->makeLiveKitToken($json),
            'CONTENT_TYPE' => 'application/json',
        ], $json)->assertOk();

        $call->refresh();
        $this->assertEquals(CallStatus::Ended, $call->status);
        $this->assertGreaterThanOrEqual(50, $call->duration_seconds);
        Event::assertDispatched(CallEnded::class);

        // Verify delayed event does not overwrite status
        $delayedPayload = [
            'id' => 'evt_delayed_finish',
            'event' => 'room_finished',
            'room' => ['name' => 'room-finish-test', 'duration' => 10],
        ];
        $jsonDelayed = json_encode($delayedPayload);
        $this->call('POST', '/api/v1/webhooks/livekit', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->makeLiveKitToken($jsonDelayed),
            'CONTENT_TYPE' => 'application/json',
        ], $jsonDelayed)->assertOk();

        $this->assertEquals(CallStatus::Ended, $call->fresh()->status);
    }
}
