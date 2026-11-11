<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_and_update_profile(): void
    {
        $user = User::factory()->withRole('user')->create([
            'full_name' => 'Original Name',
            'phone' => '0811111111',
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.full_name', 'Original Name');

        $this->putJson('/api/v1/me', [
            'full_name' => 'Updated Name',
            'phone' => '0822222222',
        ])
            ->assertOk()
            ->assertJsonPath('data.full_name', 'Updated Name')
            ->assertJsonPath('data.phone', '0822222222')
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.roles', ['user']);

        $this->putJson('/api/v1/auth/me', [
            'full_name' => 'Alias Updated Name',
            'phone' => '0833333333',
        ])
            ->assertOk()
            ->assertJsonPath('data.full_name', 'Alias Updated Name');

        $this->assertSame('Alias Updated Name', $user->fresh()->full_name);
        $this->assertSame('0833333333', $user->fresh()->phone);
    }

    public function test_medical_profile_returns_null_until_created_and_updates_fields(): void
    {
        $user = User::factory()->withRole('user')->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/me/medical-profile')
            ->assertOk()
            ->assertExactJson(['data' => null]);

        $payload = [
            'blood_type' => 'O+',
            'allergies' => ['Penisilin', 'Kacang'],
            'medical_conditions' => ['Asma', 'Hipertensi'],
            'birth_date' => '1995-08-17',
            'gender' => null,
            'weight_kg' => 68.5,
            'height_cm' => 172.0,
            'emergency_contact_name' => 'Dewi Sartika',
            'emergency_contact_relation' => 'Istri',
            'emergency_contact_phone' => '081234567899',
            'insurance' => 'BPJS Kesehatan',
            'share_on_sos' => true,
        ];

        $this->putJson('/api/v1/me/medical-profile', $payload)
            ->assertOk()
            ->assertJsonPath('data.blood_type', 'O+')
            ->assertJsonPath('data.allergies', ['Penisilin', 'Kacang'])
            ->assertJsonPath('data.medical_conditions', ['Asma', 'Hipertensi'])
            ->assertJsonPath('data.weight_kg', 68.5)
            ->assertJsonPath('data.height_cm', 172)
            ->assertJsonPath('data.emergency_contact_name', 'Dewi Sartika')
            ->assertJsonPath('data.emergency_contact_relation', 'Istri')
            ->assertJsonPath('data.emergency_contact_phone', '081234567899')
            ->assertJsonPath('data.insurance', 'BPJS Kesehatan')
            ->assertJsonPath('data.share_on_sos', true);

        $this->getJson('/api/v1/me/medical-profile')
            ->assertOk()
            ->assertJsonPath('data.blood_type', 'O+')
            ->assertJsonPath('data.allergies', ['Penisilin', 'Kacang']);
    }

    public function test_user_can_list_create_and_delete_own_addresses_only(): void
    {
        $user = User::factory()->withRole('user')->create();
        $otherUser = User::factory()->withRole('user')->create();
        $otherAddress = UserAddress::factory()->for($otherUser)->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/me/addresses')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $createRes = $this->postJson('/api/v1/me/addresses', [
            'label' => 'Rumah',
            'address' => 'Jl. Diponegoro No. 12, Menteng',
            'latitude' => -6.1985,
            'longitude' => 106.8450,
        ]);

        $createRes->assertCreated()
            ->assertJsonPath('data.label', 'Rumah')
            ->assertJsonPath('data.address', 'Jl. Diponegoro No. 12, Menteng')
            ->assertJsonPath('data.latitude', -6.1985)
            ->assertJsonPath('data.longitude', 106.8450);

        $addressId = $createRes->json('data.id');
        $this->assertNotNull($addressId);

        $this->getJson('/api/v1/me/addresses')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.label', 'Rumah');

        $this->deleteJson("/api/v1/me/addresses/{$otherAddress->id}")
            ->assertNotFound();

        $this->deleteJson("/api/v1/me/addresses/{$addressId}")
            ->assertOk();

        $this->assertDatabaseMissing('user_addresses', ['id' => $addressId]);
    }
}

