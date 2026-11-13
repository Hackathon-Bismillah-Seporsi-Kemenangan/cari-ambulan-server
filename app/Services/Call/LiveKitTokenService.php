<?php

namespace App\Services\Call;

use App\Models\Call;
use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

class LiveKitTokenService
{
    protected string $url;

    protected string $apiKey;

    protected string $apiSecret;

    protected int $tokenTtl;

    public function __construct(
        ?string $url = null,
        ?string $apiKey = null,
        ?string $apiSecret = null,
        ?int $tokenTtl = null
    ) {
        $this->url = $url ?? (string) config('livekit.url', config('services.livekit.url', ''));
        $this->apiKey = $apiKey ?? (string) config('livekit.api_key', config('services.livekit.api_key', ''));
        $this->apiSecret = $apiSecret ?? (string) config('livekit.api_secret', config('services.livekit.api_secret', ''));
        $this->tokenTtl = $tokenTtl ?? (int) config('livekit.token_ttl', config('services.livekit.token_ttl', 3600));
    }

    /**
     * Get the LiveKit Cloud WebSocket URL for client connections.
     */
    public function getLiveKitUrl(): string
    {
        $url = trim($this->url);
        if (str_starts_with($url, 'https://')) {
            return 'wss://'.substr($url, 8);
        }
        if (str_starts_with($url, 'http://')) {
            return 'ws://'.substr($url, 7);
        }

        return $url;
    }

    /**
     * Generate a signed audio-only LiveKit room access token for an authorized participant.
     *
     * @return array{server_url: string, token: string, room_name: string, identity: string, expires_in: int}
     */
    public function createAudioTokenCredentials(Call $call, User $user): array
    {
        $token = $this->createAudioToken($call, $user);

        return [
            'server_url' => $this->getLiveKitUrl(),
            'token' => $token,
            'room_name' => (string) $call->room_name,
            'identity' => (string) $user->id,
            'expires_in' => $this->tokenTtl > 0 ? $this->tokenTtl : 3600,
        ];
    }

    /**
     * Generate a signed LiveKit JWT token for an audio-only call.
     */
    public function createAudioToken(Call $call, User $user): string
    {
        $this->ensureConfigured();

        $now = Carbon::now()->timestamp;
        $ttl = $this->tokenTtl > 0 ? $this->tokenTtl : 3600;

        // Strictly audio-only: canPublish microphone, subscribe, and send data; no video publishing!
        $videoGrant = [
            'roomJoin' => true,
            'room' => $call->room_name,
            'canPublish' => true,
            'canPublishSources' => ['microphone'],
            'canSubscribe' => true,
            'canPublishData' => true,
            'roomAdmin' => false,
        ];

        $payload = [
            'iss' => $this->apiKey,
            'sub' => (string) $user->id,
            'jti' => (string) Str::uuid(),
            'nbf' => $now - 5,
            'exp' => $now + $ttl,
            'name' => $user->full_name ?? (string) $user->id,
            'video' => $videoGrant,
            'metadata' => json_encode([
                'user_id' => (string) $user->id,
                'call_id' => (string) $call->id,
                'is_caller' => (string) $call->caller_id === (string) $user->id,
                'call_type' => 'audio',
            ]),
        ];

        return JWT::encode($payload, $this->apiSecret, 'HS256');
    }

    /**
     * Backward-compatible alias for createAudioToken.
     *
     * @param  array<string, mixed>  $customGrants
     */
    public function createTokenForCall(Call $call, User $user, array $customGrants = []): string
    {
        return $this->createAudioToken($call, $user);
    }

    /**
     * Verify LiveKit webhook authorization header.
     */
    public function verifyWebhookSignature(string $body, string $authHeader): bool
    {
        if (empty($authHeader) || empty($this->apiSecret)) {
            return false;
        }

        try {
            $token = str_starts_with($authHeader, 'Bearer ')
                ? substr($authHeader, 7)
                : $authHeader;

            $decoded = JWT::decode($token, new Key($this->apiSecret, 'HS256'));

            if (! isset($decoded->iss) || $decoded->iss !== $this->apiKey) {
                return false;
            }

            if (isset($decoded->sha256)) {
                // LiveKit sends the body's SHA-256 as base64; accept hex too.
                $raw = hash('sha256', $body, true);
                if (! hash_equals(base64_encode($raw), (string) $decoded->sha256)
                    && ! hash_equals(bin2hex($raw), (string) $decoded->sha256)) {
                    return false;
                }
            }

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    protected function ensureConfigured(): void
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            throw new RuntimeException(
                'LiveKit credentials are not configured. Please set LIVEKIT_API_KEY and LIVEKIT_API_SECRET in your .env file.'
            );
        }

        if (strlen($this->apiSecret) < 32) {
            throw new RuntimeException(
                'LIVEKIT_API_SECRET must be at least 32 characters long for HS256 JWT signing.'
            );
        }
    }
}
