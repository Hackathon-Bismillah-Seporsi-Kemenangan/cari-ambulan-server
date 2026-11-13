<?php

namespace App\Services\Notification;

use App\Models\Call;
use App\Models\DeviceToken;
use App\Models\EmergencyRequest;
use App\Models\TripCall;
use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class FcmService
{
    public function __construct(
        protected ?string $projectId = null,
        protected ?string $serverKey = null
    ) {
        // The project inside the service-account file is the one that issues
        // the token, so it wins over a mistyped FCM_PROJECT_ID.
        $this->projectId = $projectId
            ?? ($this->credentials()['project_id'] ?? null)
            ?? (string) config('firebase.project_id', '');
        $this->serverKey = $serverKey ?? (string) config('firebase.server_key', '');
    }

    /**
     * The Firebase service-account file, or null when it isn't set up.
     *
     * @return array<string, mixed>|null
     */
    protected function credentials(): ?array
    {
        $path = (string) config('firebase.credentials_path', '');

        if ($path === '') {
            return null;
        }

        $absolute = str_starts_with($path, '/') || preg_match('~^[A-Za-z]:[\\\\/]~', $path);

        if (! $absolute) {
            $path = base_path($path);
        }

        if (! is_file($path)) {
            return null;
        }

        $json = json_decode((string) file_get_contents($path), true);

        return is_array($json) && isset($json['client_email'], $json['private_key']) ? $json : null;
    }

    /**
     * OAuth2 access token for FCM v1, signed with the service account and
     * cached until shortly before it expires.
     */
    protected function accessToken(): ?string
    {
        $credentials = $this->credentials();

        if ($credentials === null) {
            return null;
        }

        return Cache::remember($this->tokenCacheKey($credentials), 3300, function () use ($credentials): ?string {
            $audience = $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token';
            $now = time();

            $assertion = JWT::encode([
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => $audience,
                'iat' => $now,
                'exp' => $now + 3600,
            ], $credentials['private_key'], 'RS256');

            $response = Http::asForm()->timeout(5)->post($audience, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);

            if (! $response->successful()) {
                Log::error('FCM could not get an access token: HTTP '.$response->status().' '.$response->body());

                return null;
            }

            return $response->json('access_token');
        });
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    protected function tokenCacheKey(array $credentials): string
    {
        return 'fcm:access-token:'.md5((string) $credentials['client_email']);
    }

    /**
     * Push a data message to every active device of a user.
     *
     * @param  array<string, scalar|null>  $data
     * @return array{sent: int, failed: int, invalidated: int}
     */
    public function sendToUser(User|string $user, array $data): array
    {
        $userId = $user instanceof User ? $user->id : $user;
        $tokens = DeviceToken::query()->where('user_id', $userId)->active()->get();
        $data = array_map(fn ($value) => (string) $value, $data);

        $result = ['sent' => 0, 'failed' => 0, 'invalidated' => 0];

        foreach ($tokens as $deviceToken) {
            $outcome = $this->sendToToken($deviceToken, [], $data);

            if ($outcome === 'sent') {
                $result['sent']++;
                $deviceToken->update(['last_used_at' => now()]);
            } elseif ($outcome === 'invalid') {
                $result['failed']++;
                $result['invalidated']++;
                $deviceToken->update(['is_active' => false]);
            } else {
                $result['failed']++;
            }
        }

        return $result;
    }

    /**
     * Ring the other party of a trip call, even when their app is closed.
     *
     * @return array{sent: int, failed: int, invalidated: int}
     */
    public function sendTripCall(TripCall $call): array
    {
        $call->loadMissing('caller', 'emergencyRequest.driverProfile');
        $request = $call->emergencyRequest;

        $none = ['sent' => 0, 'failed' => 0, 'invalidated' => 0];

        if (! $request) {
            return $none;
        }

        $patientCalled = (string) $call->caller_id === (string) $request->user_id;
        $recipient = $patientCalled ? $request->driverProfile?->user_id : $request->user_id;

        if (! $recipient) {
            return $none;
        }

        return $this->sendToUser($recipient, [
            'type' => 'incoming_call',
            'call_id' => $call->id,
            'room_name' => $call->room_name ?? $call->id,
            'caller_id' => $call->caller_id,
            'caller_name' => $call->caller?->full_name ?? 'Panggilan Darurat',
            'emergency_request_id' => $request->id,
            'status' => 'ringing',
            'timestamp' => now()->timestamp,
        ]);
    }

    /**
     * Wake drivers who were just offered an emergency order.
     *
     * @param  list<string>  $driverUserIds
     */
    public function sendEmergencyOffer(EmergencyRequest $request, array $driverUserIds): void
    {
        foreach ($driverUserIds as $userId) {
            $this->sendToUser($userId, [
                'type' => 'emergency_offer',
                'emergency_request_id' => $request->id,
                'expires_in' => 30,
                'timestamp' => now()->timestamp,
            ]);
        }
    }

    /**
     * Send incoming call notification to all active devices of the receiver.
     *
     * @return array{sent: int, failed: int, invalidated: int}
     */
    public function sendIncomingCall(Call $call, User $receiver, ?string $callerName = null): array
    {
        $deviceTokens = $receiver->deviceTokens()->active()->get();

        if ($deviceTokens->isEmpty()) {
            Log::info("No active device tokens found for receiver {$receiver->id}");

            return ['sent' => 0, 'failed' => 0, 'invalidated' => 0];
        }

        $callerDisplayName = $callerName ?? $call->caller?->full_name ?? 'Someone';

        $payload = [
            'type' => 'incoming_call',
            'call_id' => (string) $call->id,
            'room_name' => (string) $call->room_name,
            'caller_id' => (string) $call->caller_id,
            'caller_name' => $callerDisplayName,
            'call_type' => 'audio',
            'status' => 'ringing',
            'timestamp' => (string) now()->timestamp,
        ];

        $sent = 0;
        $failed = 0;
        $invalidated = 0;

        foreach ($deviceTokens as $deviceToken) {
            $result = $this->sendToToken($deviceToken, [
                'title' => 'Incoming Audio Call',
                'body' => "{$callerDisplayName} is calling you...",
            ], $payload);

            if ($result === 'sent') {
                $sent++;
                $deviceToken->update(['last_used_at' => now()]);
            } elseif ($result === 'invalid') {
                $failed++;
                $invalidated++;
                $deviceToken->update(['is_active' => false]);
                Log::warning("FCM token for user {$receiver->id} marked inactive: {$deviceToken->token}");
            } else {
                $failed++;
            }
        }

        return ['sent' => $sent, 'failed' => $failed, 'invalidated' => $invalidated];
    }

    /**
     * Dispatch notification to a single device token.
     *
     * @param  array<string, string>  $notification
     * @param  array<string, string>  $data
     * @return 'sent'|'invalid'|'failed'
     */
    public function sendToToken(DeviceToken $deviceToken, array $notification, array $data): string
    {
        // If no credentials or local testing environment without FCM setup, simulate success
        if (empty($this->projectId) && empty($this->serverKey)) {
            Log::debug("Simulated FCM send to token: {$deviceToken->token}");

            return 'sent';
        }

        try {
            // Support FCM v1 API if project_id is configured
            if (! empty($this->projectId)) {
                $accessToken = $this->accessToken();

                // A service account is set up but Google refused to sign us in.
                if ($accessToken === null && $this->credentials() !== null) {
                    return 'failed';
                }

                $response = Http::timeout(3)
                    ->when($accessToken, fn ($http) => $http->withToken($accessToken))
                    ->post("https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send", [
                        'message' => [
                            'token' => $deviceToken->token,
                            'data' => $data,
                            'android' => [
                                'priority' => 'high',
                                'ttl' => '30s',
                            ],
                            'apns' => [
                                'headers' => [
                                    'apns-priority' => '10',
                                    'apns-push-type' => 'alert',
                                ],
                            ],
                        ],
                    ]);

                if ($response->successful()) {
                    return 'sent';
                }

                $status = $response->status();
                $body = $response->json();
                $errorCode = $body['error']['details'][0]['errorCode'] ?? $body['error']['status'] ?? '';

                if ($status === 401 && ($credentials = $this->credentials()) !== null) {
                    // Expired or revoked token: sign a new one next time.
                    Cache::forget($this->tokenCacheKey($credentials));
                }

                if ($status === 404 || in_array($errorCode, ['UNREGISTERED', 'INVALID_ARGUMENT'], true)) {
                    return 'invalid';
                }

                Log::warning("FCM v1 send failed: HTTP {$status} {$errorCode}");

                return 'failed';
            }

            // Legacy FCM Server Key fallback
            $response = Http::timeout(5)
                ->withHeaders([
                    'Authorization' => 'key='.$this->serverKey,
                    'Content-Type' => 'application/json',
                ])
                ->post('https://fcm.googleapis.com/fcm/send', [
                    'to' => $deviceToken->token,
                    'priority' => 'high',
                    'data' => $data,
                    'notification' => $notification,
                ]);

            if ($response->successful()) {
                $json = $response->json();
                if (($json['success'] ?? 0) === 1) {
                    return 'sent';
                }

                $error = $json['results'][0]['error'] ?? '';
                if (in_array($error, ['NotRegistered', 'InvalidRegistration'], true)) {
                    return 'invalid';
                }
            }

            return 'failed';
        } catch (Throwable $e) {
            Log::error("FCM dispatch error: {$e->getMessage()}");

            return 'failed';
        }
    }
}
