<?php

namespace Tests\Unit\Services;

use App\Models\Call;
use App\Models\User;
use App\Services\Call\LiveKitTokenService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Tests\TestCase;

class LiveKitTokenServiceTest extends TestCase
{
    public function test_generates_an_audio_only_jwt_token_with_microphone_permission_and_without_camera_permission(): void
    {
        $service = new LiveKitTokenService(
            url: 'wss://test.livekit.cloud',
            apiKey: 'test_key_1234567890',
            apiSecret: 'secret_key_must_be_at_least_32_characters_long',
            tokenTtl: 1800
        );

        $caller = new User;
        $caller->id = '11111111-1111-1111-1111-111111111111';
        $caller->full_name = 'Test Caller';

        $call = new Call;
        $call->id = '22222222-2222-2222-2222-222222222222';
        $call->caller_id = $caller->id;
        $call->room_name = 'room-audio-only-uuid';

        $token = $service->createAudioToken($call, $caller);
        $decoded = JWT::decode($token, new Key('secret_key_must_be_at_least_32_characters_long', 'HS256'));

        $this->assertSame('test_key_1234567890', $decoded->iss);
        $this->assertSame('11111111-1111-1111-1111-111111111111', $decoded->sub);
        $this->assertSame('room-audio-only-uuid', $decoded->video->room);
        $this->assertTrue($decoded->video->roomJoin);
        $this->assertTrue($decoded->video->canPublish);
        $this->assertContains('microphone', $decoded->video->canPublishSources);
        $this->assertNotContains('camera', $decoded->video->canPublishSources);
        $this->assertNotContains('screen_share', $decoded->video->canPublishSources);
        $this->assertFalse($decoded->video->roomAdmin);
    }

    public function test_verifies_a_valid_livekit_webhook_signature_and_rejects_an_invalid_one(): void
    {
        $service = new LiveKitTokenService(
            url: 'wss://test.livekit.cloud',
            apiKey: 'test_key_1234567890',
            apiSecret: 'secret_key_must_be_at_least_32_characters_long'
        );

        $body = json_encode(['event' => 'participant_joined']);
        $validToken = JWT::encode([
            'iss' => 'test_key_1234567890',
            'sha256' => hash('sha256', $body),
            'exp' => time() + 60,
        ], 'secret_key_must_be_at_least_32_characters_long', 'HS256');

        $this->assertTrue($service->verifyWebhookSignature($body, 'Bearer '.$validToken));
        $this->assertFalse($service->verifyWebhookSignature($body, 'Bearer invalid_signature'));
        $this->assertFalse($service->verifyWebhookSignature($body.'tampered', 'Bearer '.$validToken));
    }
}
