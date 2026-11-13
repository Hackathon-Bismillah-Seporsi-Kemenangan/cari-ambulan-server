<?php

namespace Tests\Feature\Call;

use App\Enums\Call\CallEndReason;
use App\Enums\Call\CallStatus;
use App\Events\Call\CallAccepted;
use App\Events\Call\CallCancelled;
use App\Events\Call\CallEnded;
use App\Events\Call\CallIncoming;
use App\Events\Call\CallRejected;
use App\Jobs\Call\ExpireRingingCall;
use App\Jobs\Call\SendIncomingCallNotification;
use App\Models\Call;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CallLifecycleTest extends TestCase
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

    public function test_allows_an_authenticated_user_to_start_a_1_on_1_audio_call(): void
    {
        Event::fake([CallIncoming::class]);
        Queue::fake([SendIncomingCallNotification::class, ExpireRingingCall::class]);

        $caller = User::factory()->create(['full_name' => 'Alice']);
        $receiver = User::factory()->create(['full_name' => 'Bob']);

        Sanctum::actingAs($caller);

        $response = $this->postJson('/api/v1/calls', [
            'receiver_id' => $receiver->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'ringing')
            ->assertJsonPath('data.caller.id', (string) $caller->id)
            ->assertJsonPath('data.receiver.id', (string) $receiver->id)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'room_name',
                    'status',
                    'caller' => ['id', 'name', 'phone'],
                    'receiver' => ['id', 'name', 'phone'],
                    'started_at',
                ],
                'connection' => [
                    'server_url',
                    'token',
                    'room_name',
                    'identity',
                    'expires_in',
                ],
            ]);

        $callId = $response->json('data.id');
        $call = Call::findOrFail($callId);

        $this->assertEquals(CallStatus::Ringing, $call->status);
        $this->assertNotEmpty($call->room_name);
        $this->assertEquals(2, $call->participants()->count());

        Queue::assertPushed(SendIncomingCallNotification::class, fn ($job) => $job->callId === $callId);
        Queue::assertPushed(ExpireRingingCall::class, fn ($job) => $job->callId === $callId);

        Event::assertDispatched(CallIncoming::class, function ($event) use ($receiver, $callId) {
            $channels = collect($event->broadcastOn())->map(fn ($c) => (string) $c)->all();
            $payload = $event->broadcastWith();

            return in_array('private-users.'.$receiver->id.'.calls', $channels, true)
                && $payload['call_id'] === $callId
                && ! isset($payload['token'])
                && ! isset($payload['api_secret']);
        });
    }

    public function test_prevents_a_user_from_calling_themselves(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/calls', [
            'receiver_id' => $user->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['receiver_id']);
    }

    public function test_prevents_initiating_a_call_when_receiver_is_busy_in_another_active_call(): void
    {
        $caller = User::factory()->create();
        $receiver = User::factory()->create();
        $thirdParty = User::factory()->create();

        Call::factory()->create([
            'caller_id' => $thirdParty->id,
            'receiver_id' => $receiver->id,
            'status' => CallStatus::Accepted,
        ]);

        Sanctum::actingAs($caller);

        $this->postJson('/api/v1/calls', [
            'receiver_id' => $receiver->id,
        ])->assertStatus(409)->assertSee('User tujuan sedang dalam panggilan lain.');
    }

    public function test_prevents_initiating_a_call_when_caller_is_already_in_an_active_call(): void
    {
        $caller = User::factory()->create();
        $receiver = User::factory()->create();
        $thirdParty = User::factory()->create();

        Call::factory()->create([
            'caller_id' => $caller->id,
            'receiver_id' => $thirdParty->id,
            'status' => CallStatus::Ringing,
        ]);

        Sanctum::actingAs($caller);

        $this->postJson('/api/v1/calls', [
            'receiver_id' => $receiver->id,
        ])->assertStatus(409)->assertSee('Anda masih memiliki panggilan yang sedang berlangsung.');
    }

    public function test_allows_the_receiver_to_accept_a_ringing_call_and_receive_credentials(): void
    {
        Event::fake([CallAccepted::class]);

        $caller = User::factory()->create();
        $receiver = User::factory()->create();
        $call = Call::factory()->create([
            'caller_id' => $caller->id,
            'receiver_id' => $receiver->id,
            'status' => CallStatus::Ringing,
        ]);

        Sanctum::actingAs($receiver);

        $response = $this->postJson("/api/v1/calls/{$call->id}/accept");

        $response->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonStructure([
                'data' => ['id', 'status', 'answered_at'],
                'connection' => ['server_url', 'token', 'room_name', 'identity', 'expires_in'],
            ]);

        $call->refresh();
        $this->assertEquals(CallStatus::Accepted, $call->status);
        $this->assertNotNull($call->answered_at);

        Event::assertDispatched(CallAccepted::class, function ($event) use ($caller, $receiver) {
            $channels = collect($event->broadcastOn())->map(fn ($c) => (string) $c)->all();

            return in_array('private-users.'.$caller->id.'.calls', $channels, true)
                && in_array('private-users.'.$receiver->id.'.calls', $channels, true);
        });
    }

    public function test_forbids_caller_or_strangers_from_accepting_a_call(): void
    {
        $caller = User::factory()->create();
        $receiver = User::factory()->create();
        $stranger = User::factory()->create();
        $call = Call::factory()->create([
            'caller_id' => $caller->id,
            'receiver_id' => $receiver->id,
            'status' => CallStatus::Ringing,
        ]);

        Sanctum::actingAs($caller);
        $this->postJson("/api/v1/calls/{$call->id}/accept")->assertForbidden();

        Sanctum::actingAs($stranger);
        $this->postJson("/api/v1/calls/{$call->id}/accept")->assertForbidden();
    }

    public function test_cannot_accept_a_call_if_not_ringing(): void
    {
        $caller = User::factory()->create();
        $receiver = User::factory()->create();
        $call = Call::factory()->ended()->create([
            'caller_id' => $caller->id,
            'receiver_id' => $receiver->id,
        ]);

        Sanctum::actingAs($receiver);
        $this->postJson("/api/v1/calls/{$call->id}/accept")->assertStatus(409);
    }

    public function test_allows_receiver_to_reject_a_ringing_call(): void
    {
        Event::fake([CallRejected::class]);

        $caller = User::factory()->create();
        $receiver = User::factory()->create();
        $call = Call::factory()->create([
            'caller_id' => $caller->id,
            'receiver_id' => $receiver->id,
            'status' => CallStatus::Ringing,
        ]);

        Sanctum::actingAs($receiver);

        $response = $this->postJson("/api/v1/calls/{$call->id}/reject");

        $response->assertOk()->assertJsonPath('data.status', 'rejected');

        $call->refresh();
        $this->assertEquals(CallStatus::Rejected, $call->status);
        $this->assertEquals(CallEndReason::Rejected, $call->end_reason);
        $this->assertNotNull($call->ended_at);

        Event::assertDispatched(CallRejected::class);
    }

    public function test_allows_caller_to_cancel_a_ringing_call(): void
    {
        Event::fake([CallCancelled::class]);

        $caller = User::factory()->create();
        $receiver = User::factory()->create();
        $call = Call::factory()->create([
            'caller_id' => $caller->id,
            'receiver_id' => $receiver->id,
            'status' => CallStatus::Ringing,
        ]);

        Sanctum::actingAs($caller);

        $response = $this->postJson("/api/v1/calls/{$call->id}/cancel");

        $response->assertOk()->assertJsonPath('data.status', 'cancelled');

        $call->refresh();
        $this->assertEquals(CallStatus::Cancelled, $call->status);
        $this->assertEquals(CallEndReason::CallerEnded, $call->end_reason);

        Event::assertDispatched(CallCancelled::class);
    }

    public function test_allows_caller_or_receiver_to_end_an_active_call(): void
    {
        Event::fake([CallEnded::class]);

        $caller = User::factory()->create();
        $receiver = User::factory()->create();
        $call = Call::factory()->accepted()->create([
            'caller_id' => $caller->id,
            'receiver_id' => $receiver->id,
            'answered_at' => now()->subSeconds(40),
        ]);

        Sanctum::actingAs($receiver);

        $response = $this->postJson("/api/v1/calls/{$call->id}/end");

        $response->assertOk()->assertJsonPath('data.status', 'ended');

        $call->refresh();
        $this->assertEquals(CallStatus::Ended, $call->status);
        $this->assertEquals(CallEndReason::ReceiverEnded, $call->end_reason);
        $this->assertNotNull($call->ended_at);
        $this->assertGreaterThanOrEqual(40, $call->duration_seconds);

        Event::assertDispatched(CallEnded::class);
    }

    public function test_forbids_stranger_from_ending_a_call(): void
    {
        $caller = User::factory()->create();
        $receiver = User::factory()->create();
        $stranger = User::factory()->create();
        $call = Call::factory()->accepted()->create([
            'caller_id' => $caller->id,
            'receiver_id' => $receiver->id,
        ]);

        Sanctum::actingAs($stranger);
        $this->postJson("/api/v1/calls/{$call->id}/end")->assertForbidden();
    }
}
