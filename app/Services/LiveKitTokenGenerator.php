<?php

namespace App\Services;

use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

class LiveKitTokenGenerator
{
    protected string $url;

    protected string $apiKey;

    protected string $apiSecret;

    protected int $tokenTtl;

    public function __construct(
        ?string $url = null,
        ?string $apiKey = null,
        ?string $apiSecret = null,
        ?int $tokenTtl = null,
    ) {
        $this->url = $url ?? (string) config('services.livekit.url', '');
        $this->apiKey = $apiKey ?? (string) config('services.livekit.api_key', '');
        $this->apiSecret = $apiSecret ?? (string) config('services.livekit.api_secret', '');
        $this->tokenTtl = $tokenTtl ?? (int) config('services.livekit.token_ttl', 3600);
    }

    public function url(): string
    {
        return $this->url;
    }

    public function ttl(): int
    {
        return $this->tokenTtl > 0 ? $this->tokenTtl : 3600;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $grants
     */
    public function forRoom(string $room, User $user, array $metadata = [], array $grants = []): string
    {
        $this->ensureConfigured();

        $now = Carbon::now()->timestamp;
        $ttl = $this->ttl();

        $videoGrant = array_merge([
            'roomJoin' => true,
            'room' => $room,
            'canPublish' => true,
            'canSubscribe' => true,
            'canPublishData' => true,
        ], $grants);

        $payload = [
            'iss' => $this->apiKey,
            'sub' => (string) $user->id,
            'jti' => (string) Str::uuid(),
            'nbf' => $now - 5,
            'exp' => $now + $ttl,
            'name' => $user->full_name ?? (string) $user->id,
            'video' => $videoGrant,
            'metadata' => json_encode(array_merge(['user_id' => $user->id], $metadata)),
        ];

        return JWT::encode($payload, $this->apiSecret, 'HS256');
    }

    protected function ensureConfigured(): void
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            throw new RuntimeException('LiveKit credentials are not configured. Please set LIVEKIT_API_KEY and LIVEKIT_API_SECRET in your .env file.');
        }

        if (strlen($this->apiSecret) < 32) {
            throw new RuntimeException('LIVEKIT_API_SECRET must be at least 32 characters long for HS256 JWT signing.');
        }
    }
}
