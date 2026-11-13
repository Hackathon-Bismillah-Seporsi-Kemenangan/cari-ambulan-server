<?php

namespace Tests\Feature\Call;

use App\Models\Call;
use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CallTokenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'livekit.url' => 'wss://test.livekit.cloud',
            'livekit.api_key' => 'test_api_key_1234567890',
            'livekit.api_secret' => 'supersecretkey_must_be_32_characters_long_min',
            'livekit.token_ttl' => 3600,
        ]);
    }

    public function test_allows_an_authorized_participant_to_request_a_token_and_ensures_audio_only_permissions(): void
    {
        $caller = User::factory()->create();
        $receiver = User::factory()->create();
        $call = Call::factory()->accepted()->create([
            'caller_id' => $caller->id,
            'receiver_id' => $receiver->id,
        ]);

        Sanctum::actingAs($caller);

        $response = $this->postJson("/api/v1/calls/{$call->id}/token");

        $response->assertOk()
            ->assertJsonStructure(['server_url', 'token', 'room_name', 'identity', 'expires_in'])
            ->assertJsonPath('server_url', 'wss://test.livekit.cloud')
            ->assertJsonPath('room_name', (string) $call->room_name)
            ->assertJsonPath('identity', (string) $caller->id);

        $jwt = $response->json('token');
        $secret = config('livekit.api_secret');
        $decoded = JWT::decode($jwt, new Key($secret, 'HS256'));

        // Check grants: strictly audio-only!
        $this->assertTrue($decoded->video->roomJoin);
        $this->assertSame($call->room_name, $decoded->video->room);
        $this->assertTrue($decoded->video->canPublish);
        $this->assertContains('microphone', $decoded->video->canPublishSources);
        $this->assertNotContains('camera', $decoded->video->canPublishSources);
        $this->assertNotContains('screen_share', $decoded->video->canPublishSources);
        $this->assertTrue($decoded->video->canSubscribe);
        $this->assertTrue($decoded->video->canPublishData);
        $this->assertFalse($decoded->video->roomAdmin);
    }

    public function test_forbids_strangers_from_requesting_participant_token(): void
    {
        $caller = User::factory()->create();
        $receiver = User::factory()->create();
        $stranger = User::factory()->create();
        $call = Call::factory()->accepted()->create([
            'caller_id' => $caller->id,
            'receiver_id' => $receiver->id,
        ]);

        Sanctum::actingAs($stranger);

        $this->postJson("/api/v1/calls/{$call->id}/token")->assertForbidden();
    }

    public function test_prevents_requesting_token_if_call_is_ended(): void
    {
        $caller = User::factory()->create();
        $receiver = User::factory()->create();
        $call = Call::factory()->ended()->create([
            'caller_id' => $caller->id,
            'receiver_id' => $receiver->id,
        ]);

        Sanctum::actingAs($caller);

        $this->postJson("/api/v1/calls/{$call->id}/token")->assertStatus(409);
    }
}
