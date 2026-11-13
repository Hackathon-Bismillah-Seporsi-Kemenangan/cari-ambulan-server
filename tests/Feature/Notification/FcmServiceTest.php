<?php

namespace Tests\Feature\Notification;

use App\Enums\DriverAvailability;
use App\Enums\EmergencyStatus;
use App\Models\DeviceToken;
use App\Models\DriverProfile;
use App\Models\EmergencyRequest;
use App\Models\TripCall;
use App\Services\Notification\FcmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class FcmServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ?string $tempCredentialsPath = null;

    protected function tearDown(): void
    {
        if ($this->tempCredentialsPath && is_file($this->tempCredentialsPath)) {
            unlink($this->tempCredentialsPath);
        }

        parent::tearDown();
    }

    private function fakeServiceAccount(): string
    {
        $privateKey = <<<'PEM'
-----BEGIN PRIVATE KEY-----
MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQCI56ZCjib6th96
VXzbPGdnezRbGy5jcIXWw6A/S52jJ1bxJkyM64ZBKRvy7Hlv4/L5UZpgkVpWkL2c
nb1Pxwb9XFd8A7acF6jzH4olG362WYgOU3Sjs7IhMnS+0Zx3BDjPgyN/uoTCBN1g
N5KuKSenqd/NMMmfBr1/Ti59dlsJHhfeMkq6U863cwOH/6xhS1G2Cf62FAaIq33D
/JoyXV3/aFD9lGAulhIWZcqVNQi3h1POFZsyClBAZyfulyzAynIna8YJ6weuCZ6f
nimfWE2sznhbaOBpR4LyTXe1pZHwWzn4viPUMxIGOmCUwR98aQwpL14BzPC34+OD
nx4iF19fAgMBAAECggEADVuKFZz9tqEwabo6PWsjrQK7YAGYlsEy0yUxLHEknKJI
/FcVSaaX6cxTWsdcgzXwI11P57N8PKKmAshCjAHKRSF5CQjqYfPOWzJXmI6Z4BF/
keOYp3manAc/7CJS1tXwSRGYKW05vh3FZQ7nCTEgxQjGZPnT95KBHhEIaNtfFIDY
9PjWn1g7J3DqdzEKMmhHjlDD+JVDYXhYx+yMoSCjcVWvjfOR5Y9SsR9/byPhrphY
dCBxcOEWkctTJQygoIPDoLjEdCogLyJoCAcWueRNU3HU6AttKOHPcj5NVYeTiUiN
81Op+wbhMVyKx7g+HAk8iD4/BPjVtAfo+UhHfnSlqQKBgQC+jTjsq0BEXOgedYwl
xwJVxR1hp0FNgUNJDgt7OUcJmXJ+O9gJjfslXqlquZFImiBQQ/rfyp9dWQ9FvGy8
Bjimqz0WBqL+FRLlAa5STv81Ii/sO9nctXT2104WRU7wWptFblvfS/1QGpJu9GY4
JeHuZsJEcf+z/z1zXNu10TIRcwKBgQC37WMvGnPHbyhzjc6T2Y/jbc8EDh6r5QcL
DLZQPi18xLgQ/IQfEPnTRwBz0s5NSZZLMoY7Lyj+FApZx7vm7za8Nk4AaB7vDpPe
89/09EdZ+DQQ0a0nKVdOFqFk8SY9MeyqXBCfhLFFew9uCGEuxtp5V2TDECXZQH4Y
c+bH9TxPZQKBgQCqqIJt/GX/lBHTjcVp5BVkSbipyAkFaBqh6DFCOCc2IrbW9bPj
wsRGUMR8sBu9IUSFfozGN4uJKAU0mK/tqci9BKod+u2pj6gvb9T3JIcOyEKRWX0i
XWWW2Xe3HHW5b/yWL2Oa1kzVnCa/tqWB7fOt91xPvqWbSKJ5mpWv/d7QnwKBgDM/
tRRJ5ZrbcUUJbVcrFvnC/hzeJlO1REoi56XiPWB3m+Y3xkS1wTbKoOilA5DrgTJA
yMAy+tQZF20+xy+o1fKJmyBn4r6vrCpCf9PMQRljnHZXRk0UKtx6V8w9mW8RuyUY
9BTOr2pxyLHbKeETzYpj/JKRrEoluzzrBpIO1hg1AoGBAJfrv024QW8980rMBHoE
PFparAhWygLZGJcBonw2bXGuwjxs2G4I6mHfQUlMTdvE1e5Ekdr9NJnuJR0XKLOd
oZHXOrblc9sJGTFyZ/JyHfgSA4PXetDLu+Qe8DUTzPSCejLvmNLDf3NbNPAGyX/J
iGk0sgHaP4jSzWGQopQ964+/
-----END PRIVATE KEY-----
PEM;

        $path = tempnam(sys_get_temp_dir(), 'sa').'.json';
        file_put_contents($path, json_encode([
            'type' => 'service_account',
            'project_id' => 'cari-ambulan',
            'private_key' => $privateKey,
            'client_email' => 'firebase-adminsdk@cari-ambulan.iam.gserviceaccount.com',
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]));

        config([
            'firebase.credentials_path' => $path,
            'firebase.project_id' => 'cari_ambulan',
            'firebase.server_key' => '',
        ]);
        Cache::flush();

        $this->tempCredentialsPath = $path;

        return $path;
    }

    private function assignedTrip(DriverProfile $driver, EmergencyStatus $status): EmergencyRequest
    {
        $driver->update(['availability_status' => DriverAvailability::Busy->value]);

        return EmergencyRequest::factory()->create([
            'status' => $status->value,
            'driver_profile_id' => $driver->id,
            'ambulance_id' => $driver->ambulance->id,
            'accepted_at' => now(),
        ]);
    }

    public function test_signs_in_with_service_account_and_sends_with_bearer_token(): void
    {
        $this->fakeServiceAccount();
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.test', 'expires_in' => 3600]),
            'fcm.googleapis.com/*' => Http::response(['name' => 'projects/cari-ambulan/messages/1']),
        ]);

        $token = DeviceToken::factory()->create();

        $result = app(FcmService::class)->sendToUser($token->user_id, ['type' => 'emergency_offer', 'expires_in' => 30]);

        $this->assertEquals(['sent' => 1, 'failed' => 0, 'invalidated' => 0], $result);

        Http::assertSent(fn (Request $r) => $r->url() === 'https://oauth2.googleapis.com/token'
            && $r['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
            && count(explode('.', $r['assertion'])) === 3);

        Http::assertSent(fn (Request $r) => $r->url() === 'https://fcm.googleapis.com/v1/projects/cari-ambulan/messages:send'
            && $r->hasHeader('Authorization', 'Bearer ya29.test')
            && $r['message']['token'] === $token->token
            && $r['message']['data'] === ['type' => 'emergency_offer', 'expires_in' => '30']
            && $r['message']['android']['priority'] === 'high');
    }

    public function test_reuses_the_cached_access_token_instead_of_signing_in_for_every_push(): void
    {
        $this->fakeServiceAccount();
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.test', 'expires_in' => 3600]),
            'fcm.googleapis.com/*' => Http::response([]),
        ]);

        $user = DeviceToken::factory()->create()->user;
        DeviceToken::factory()->create(['user_id' => $user->id]);

        app(FcmService::class)->sendToUser($user, ['type' => 'x']);
        app(FcmService::class)->sendToUser($user, ['type' => 'x']);

        Http::assertSentCount(5); // 1 sign-in + 4 sends
    }

    public function test_deactivates_a_device_token_google_reports_as_unregistered(): void
    {
        $this->fakeServiceAccount();
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.test', 'expires_in' => 3600]),
            'fcm.googleapis.com/*' => Http::response(['error' => ['status' => 'NOT_FOUND', 'details' => [['errorCode' => 'UNREGISTERED']]]], 404),
        ]);

        $token = DeviceToken::factory()->create();

        $result = app(FcmService::class)->sendToUser($token->user_id, ['type' => 'x']);

        $this->assertEquals(1, $result['invalidated']);
        $this->assertFalse($token->fresh()->is_active);
    }

    public function test_reports_a_failure_when_google_refuses_the_service_account(): void
    {
        $this->fakeServiceAccount();
        Http::fake(['oauth2.googleapis.com/*' => Http::response(['error' => 'invalid_grant'], 400)]);

        $token = DeviceToken::factory()->create();

        $result = app(FcmService::class)->sendToUser($token->user_id, ['type' => 'x']);

        $this->assertEquals(0, $result['sent']);
        $this->assertEquals(1, $result['failed']);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'fcm.googleapis.com'));
    }

    public function test_does_nothing_over_the_network_when_fcm_is_not_configured(): void
    {
        config(['firebase.credentials_path' => '', 'firebase.project_id' => '', 'firebase.server_key' => '']);
        Http::fake();

        $token = DeviceToken::factory()->create();

        $this->assertEquals(1, app(FcmService::class)->sendToUser($token->user_id, ['type' => 'x'])['sent']);
        Http::assertNothingSent();
    }

    public function test_rings_the_patient_when_the_driver_placed_the_trip_call_and_vice_versa(): void
    {
        config(['firebase.credentials_path' => '', 'firebase.project_id' => '', 'firebase.server_key' => '']);

        $driver = DriverProfile::factory()->withAmbulance()->create();
        $trip = $this->assignedTrip($driver, EmergencyStatus::EnRouteToPickup);
        $patientToken = DeviceToken::factory()->create(['user_id' => $trip->user_id]);
        $driverToken = DeviceToken::factory()->create(['user_id' => $driver->user_id]);

        $service = Mockery::mock(FcmService::class)->makePartial();
        $sent = [];
        $service->shouldReceive('sendToToken')->andReturnUsing(function (DeviceToken $token, $n, $data) use (&$sent) {
            $sent[$token->id] = $data;

            return 'sent';
        });

        $fromDriver = TripCall::factory()->for($trip)->create(['caller_id' => $driver->user_id]);
        $service->sendTripCall($fromDriver);
        $this->assertEquals([$patientToken->id], array_keys($sent));
        $this->assertEquals('incoming_call', $sent[$patientToken->id]['type']);
        $this->assertEquals((string) $fromDriver->id, $sent[$patientToken->id]['call_id']);

        $sent = [];
        $fromPatient = TripCall::factory()->for($trip)->create(['caller_id' => $trip->user_id]);
        $service->sendTripCall($fromPatient);
        $this->assertEquals([$driverToken->id], array_keys($sent));
    }
}
