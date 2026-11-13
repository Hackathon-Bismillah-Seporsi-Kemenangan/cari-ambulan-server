<?php

namespace App\Services\Call;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class LiveKitService
{
    protected string $url;

    protected string $apiKey;

    protected string $apiSecret;

    protected int $emptyTimeout;

    protected int $maxDuration;

    public function __construct(
        ?string $url = null,
        ?string $apiKey = null,
        ?string $apiSecret = null,
        ?int $emptyTimeout = null,
        ?int $maxDuration = null
    ) {
        $this->url = $url
            ?? (string) (config('livekit.api_url') ?: config('livekit.url', config('services.livekit.url', '')));
        $this->apiKey = $apiKey ?? (string) config('livekit.api_key', config('services.livekit.api_key', ''));
        $this->apiSecret = $apiSecret ?? (string) config('livekit.api_secret', config('services.livekit.api_secret', ''));
        $this->emptyTimeout = $emptyTimeout ?? (int) config('livekit.empty_timeout', 300);
        $this->maxDuration = $maxDuration ?? (int) config('livekit.max_duration', 7200);
    }

    /**
     * Generate a unique room name for a call.
     */
    public function generateRoomName(): string
    {
        return (string) Str::uuid();
    }

    /**
     * Get the HTTP API Base URL from LiveKit URL (converting wss:// to https://).
     */
    public function getHttpBaseUrl(): string
    {
        $url = trim($this->url);
        if (str_starts_with($url, 'wss://')) {
            return 'https://'.substr($url, 6);
        }
        if (str_starts_with($url, 'ws://')) {
            return 'http://'.substr($url, 5);
        }

        return rtrim($url, '/');
    }

    /**
     * Create an audio-only 1-on-1 room on LiveKit Cloud.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>|null
     */
    public function createRoom(string $roomName, array $options = []): ?array
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            Log::info("LiveKit credentials not set. Simulated room creation for: {$roomName}");

            return [
                'name' => $roomName,
                'empty_timeout' => $this->emptyTimeout,
                'max_participants' => 2,
            ];
        }

        try {
            $token = $this->createAdminToken();
            $baseUrl = $this->getHttpBaseUrl();

            $response = Http::timeout(5)
                ->withToken($token)
                ->post("{$baseUrl}/twirp/livekit.RoomService/CreateRoom", array_merge([
                    'name' => $roomName,
                    'empty_timeout' => $this->emptyTimeout,
                    'max_participants' => 2,
                ], $options));

            if ($response->successful()) {
                return $response->json();
            }

            Log::error("Failed to create LiveKit room {$roomName}: HTTP {$response->status()} - {$response->body()}");

            return null;
        } catch (Throwable $e) {
            Log::error("Exception creating LiveKit room {$roomName}: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Retrieve room details from LiveKit Cloud.
     *
     * @return array<string, mixed>|null
     */
    public function getRoom(string $roomName): ?array
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            return ['name' => $roomName];
        }

        try {
            $token = $this->createAdminToken();
            $baseUrl = $this->getHttpBaseUrl();

            $response = Http::timeout(5)
                ->withToken($token)
                ->post("{$baseUrl}/twirp/livekit.RoomService/ListRooms", [
                    'names' => [$roomName],
                ]);

            if ($response->successful()) {
                $rooms = $response->json('rooms') ?? [];

                return $rooms[0] ?? null;
            }

            return null;
        } catch (Throwable $e) {
            Log::error("Exception retrieving LiveKit room {$roomName}: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * List all participants in a room.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listParticipants(string $roomName): array
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            return [];
        }

        try {
            $token = $this->createAdminToken();
            $baseUrl = $this->getHttpBaseUrl();

            $response = Http::timeout(5)
                ->withToken($token)
                ->post("{$baseUrl}/twirp/livekit.RoomService/ListParticipants", [
                    'room' => $roomName,
                ]);

            if ($response->successful()) {
                return $response->json('participants') ?? [];
            }

            return [];
        } catch (Throwable $e) {
            Log::error("Exception listing participants for {$roomName}: {$e->getMessage()}");

            return [];
        }
    }

    /**
     * Remove a participant from a room.
     */
    public function removeParticipant(string $roomName, string $identity): bool
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            return true;
        }

        try {
            $token = $this->createAdminToken();
            $baseUrl = $this->getHttpBaseUrl();

            $response = Http::timeout(5)
                ->withToken($token)
                ->post("{$baseUrl}/twirp/livekit.RoomService/RemoveParticipant", [
                    'room' => $roomName,
                    'identity' => $identity,
                ]);

            return $response->successful();
        } catch (Throwable $e) {
            Log::error("Exception removing participant {$identity} from {$roomName}: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Delete an audio room on LiveKit Cloud.
     */
    public function deleteRoom(string $roomName): bool
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            Log::info("LiveKit credentials not set. Simulated delete room: {$roomName}");

            return true;
        }

        try {
            $token = $this->createAdminToken();
            $baseUrl = $this->getHttpBaseUrl();

            $response = Http::timeout(5)
                ->withToken($token)
                ->post("{$baseUrl}/twirp/livekit.RoomService/DeleteRoom", [
                    'room' => $roomName,
                ]);

            return $response->successful();
        } catch (Throwable $e) {
            Log::error("Exception deleting LiveKit room {$roomName}: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Create short-lived admin JWT token for LiveKit server API requests.
     */
    public function createAdminToken(): string
    {
        $now = time();
        $payload = [
            'iss' => $this->apiKey,
            'nbf' => $now - 5,
            'exp' => $now + 600,
            'video' => [
                'roomCreate' => true,
                'roomAdmin' => true,
                'roomList' => true,
                'roomRecord' => true,
            ],
        ];

        return JWT::encode($payload, $this->apiSecret, 'HS256');
    }
}
