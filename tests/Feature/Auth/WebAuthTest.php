<?php

namespace Tests\Feature\Auth;

use App\Models\Hospital;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WebAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_guest_can_view_login_form(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk()
            ->assertSee('Portal Masuk')
            ->assertSee('Alamat Email')
            ->assertSee('Kata Sandi');
    }

    public function test_guest_can_view_register_form(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk()
            ->assertSee('Daftarkan rumah sakit Anda')
            ->assertSee('Nama rumah sakit')
            ->assertSee('Telepon IGD');
    }

    public function test_hospital_admin_can_register_with_hospital(): void
    {
        $payload = [
            'full_name' => 'Dr. H. Bambang Subagyo',
            'email' => 'dr.bambang@rsmitra.id',
            'phone' => '081234567890',
            'password' => 'Hospital@1234',
            'password_confirmation' => 'Hospital@1234',
            'hospital_name' => 'RS Mitra Kasih Sejahtera',
            'hospital_address' => 'Jl. Kesehatan No. 45, Jakarta Pusat',
            'emergency_phone' => '021-5550999',
            'ambulance_entrance' => 'Gerbang Barat Khusus Ambulans',
            'latitude' => -6.2088,
            'longitude' => 106.8222,
        ];

        $response = $this->post(route('register.attempt'), $payload);

        $response->assertRedirect(route('dashboard.hospital'))
            ->assertSessionHas('success');

        $this->assertAuthenticated();

        $user = User::where('email', 'dr.bambang@rsmitra.id')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('hospital_admin'));

        $hospital = Hospital::where('name', 'RS Mitra Kasih Sejahtera')->first();
        $this->assertNotNull($hospital);
        $this->assertEquals($user->id, $hospital->admin_user_id);
    }

    public function test_hospital_admin_can_login_to_web(): void
    {
        $user = User::factory()->withRole('hospital_admin')->create([
            'email' => 'hospital.admin@test.com',
            'password' => Hash::make('Secret123'),
        ]);
        Hospital::factory()->create(['admin_user_id' => $user->id]);

        $response = $this->post(route('login.attempt'), [
            'email' => 'hospital.admin@test.com',
            'password' => 'Secret123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_driver_or_civilian_cannot_login_to_web_portal(): void
    {
        $driver = User::factory()->withRole('driver')->create([
            'email' => 'driver@test.com',
            'password' => Hash::make('Secret123'),
        ]);

        $response = $this->post(route('login.attempt'), [
            'email' => 'driver@test.com',
            'password' => 'Secret123',
        ]);

        $response->assertRedirect()
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->withRole('administrator')->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
