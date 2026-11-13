<?php

namespace Tests\Feature\DeviceToken;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeviceTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_allows_an_authenticated_user_to_register_a_new_fcm_device_token(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/device-tokens', [
            'token' => 'fcm_test_token_1234567890',
            'device_id' => 'device_abc',
            'device_type' => 'android',
            'device_name' => 'Pixel 8 Pro',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.token', 'fcm_test_token_1234567890')
            ->assertJsonPath('data.device_type', 'android')
            ->assertJsonPath('data.device_name', 'Pixel 8 Pro')
            ->assertJsonPath('data.is_active', true);

        $this->assertSame(1, DeviceToken::where('token', 'fcm_test_token_1234567890')->count());
    }

    public function test_updates_and_reassigns_an_existing_fcm_token_when_a_new_user_logs_in_on_that_device(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $token = DeviceToken::factory()->create([
            'user_id' => $user1->id,
            'token' => 'shared_fcm_token_xyz',
            'device_name' => 'Old Device',
            'is_active' => false,
        ]);

        Sanctum::actingAs($user2);

        $response = $this->postJson('/api/v1/device-tokens', [
            'token' => 'shared_fcm_token_xyz',
            'device_type' => 'ios',
            'device_name' => 'iPhone 15',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user_id', (string) $user2->id)
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.device_name', 'iPhone 15');

        $token->refresh();
        $this->assertSame((string) $user2->id, (string) $token->user_id);
        $this->assertTrue($token->is_active);
    }

    public function test_lists_all_registered_tokens_belonging_to_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        DeviceToken::factory()->count(2)->create(['user_id' => $user->id]);
        DeviceToken::factory()->create(['user_id' => $other->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/device-tokens');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_allows_a_user_to_delete_their_registered_fcm_device_token(): void
    {
        $user = User::factory()->create();
        $token = DeviceToken::factory()->create([
            'user_id' => $user->id,
            'token' => 'token_to_remove',
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/v1/device-tokens', [
            'token' => 'token_to_remove',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Device token removed successfully.');

        $this->assertFalse(DeviceToken::where('token', 'token_to_remove')->exists());
    }

    public function test_prevents_a_user_from_deleting_another_users_device_token(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $otherToken = DeviceToken::factory()->create([
            'user_id' => $other->id,
            'token' => 'other_user_token',
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/v1/device-tokens', [
            'token' => 'other_user_token',
        ]);

        $response->assertOk();
        $this->assertNotNull($otherToken->fresh());
    }
}
