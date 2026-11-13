<?php

namespace Tests\Feature\Http\Controllers\Dashboard;

use App\Enums\DriverAvailability;
use App\Enums\EmergencyStatus;
use App\Models\Ambulance;
use App\Models\DriverProfile;
use App\Models\EmergencyRequest;
use App\Models\Hospital;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_renders_web_dashboard_for_administrator(): void
    {
        $user = User::factory()->withRole('administrator')->create();

        $response = $this->actingAs($user)->get('/dashboard/admin');

        $response->assertOk();
    }

    public function test_renders_web_dashboard_for_hospital_admin(): void
    {
        $user = User::factory()->withRole('hospital_admin')->create();
        $hospital = Hospital::factory()->create(['admin_user_id' => $user->id]);

        $response = $this->actingAs($user)->get('/dashboard/hospital');

        $response->assertOk();
    }

    public function test_redirects_driver_and_civilian_roles_away_from_web_dashboard(): void
    {
        $driver = User::factory()->withRole('driver')->create();
        $civilian = User::factory()->withRole('user')->create();

        $res1 = $this->actingAs($driver)->get('/dashboard');
        $res1->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $this->assertGuest();

        $res2 = $this->actingAs($civilian)->get('/dashboard');
        $res2->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_displays_users_roles_management_and_hospital_list_on_admin_dashboard(): void
    {
        $admin = User::factory()->withRole('administrator')->create();
        $hospital = Hospital::factory()->create(['name' => 'RSUD Tarakan Jakarta']);
        $role = Role::firstOrCreate(['name' => 'paramedic']);

        $response = $this->actingAs($admin)->get(route('dashboard.admin'));

        $response->assertOk()
            ->assertSee('Manajemen Pengguna')
            ->assertSee('Manajemen Role')
            ->assertSee('Daftar Rumah Sakit')
            ->assertSee('RSUD Tarakan Jakarta')
            ->assertSee($role->name);
    }

    public function test_renders_redesigned_admin_dashboard_with_operational_tabs(): void
    {
        $admin = User::factory()->withRole('administrator')->create();
        $hospital = Hospital::factory()->create(['name' => 'RSUD Fatmawati']);
        $role = Role::firstOrCreate(['name' => 'doctor']);

        $response = $this->actingAs($admin)->get(route('dashboard.admin'));

        $response->assertOk()
            ->assertSee('Pusat Komando Administrator')
            ->assertSee('data-tab-target="overview"', false)
            ->assertSee('data-tab-target="users"', false)
            ->assertSee('data-tab-target="roles"', false)
            ->assertSee('data-tab-target="hospitals"', false)
            ->assertSee('data-tab-content="overview"', false)
            ->assertSee('data-tab-content="users"', false)
            ->assertSee('data-tab-content="roles"', false)
            ->assertSee('data-tab-content="hospitals"', false)
            ->assertSee('RSUD Fatmawati')
            ->assertSee($role->name);
    }

    public function test_renders_redesigned_hospital_dashboard_with_tabs_and_triage(): void
    {
        $hospital = Hospital::factory()->create([
            'name' => 'RSUP Persahabatan',
            'ambulance_entrance' => 'Gerbang C - Jalur Evakuasi Cepat',
            'emergency_phone' => '021-4891708',
        ]);

        $driver = DriverProfile::factory()->create(['hospital_id' => $hospital->id]);
        $hospitalUser = User::factory()->withRole('hospital_admin')->create();
        $hospital->update(['admin_user_id' => $hospitalUser->id]);

        $ambulance = Ambulance::factory()->create([
            'hospital_id' => $hospital->id,
            'driver_profile_id' => $driver->id,
            'plate_number' => 'B 9999 IGD',
        ]);

        EmergencyRequest::factory()->create([
            'hospital_id' => $hospital->id,
            'driver_profile_id' => $driver->id,
            'ambulance_id' => $ambulance->id,
            'emergency_type' => 'Sesak Nafas',
            'patient_notes' => 'Pasien butuh oksigen segera',
            'status' => EmergencyStatus::DriverAssigned->value,
        ]);

        $response = $this->actingAs($hospitalUser)->get(route('dashboard.hospital'));

        $response->assertOk()
            ->assertSee('RSUP Persahabatan')
            ->assertSee('Gerbang C - Jalur Evakuasi Cepat')
            ->assertSee('021-4891708')
            ->assertSee('B 9999 IGD')
            ->assertSee('Operasional IGD')
            ->assertSee('data-tab-target="permintaan"', false)
            ->assertSee('data-tab-target="driver"', false)
            ->assertSee('data-tab-target="faskes"', false)
            ->assertSee('data-tab-content="permintaan"', false)
            ->assertSee('data-tab-content="driver"', false)
            ->assertSee('data-tab-content="faskes"', false)
            ->assertSee('IGD menyiapkan tim pernapasan.')
            ->assertSee('Pasien butuh oksigen segera');
    }

    public function test_shows_setup_hospital_form_when_hospital_admin_has_no_linked_hospital(): void
    {
        $hospitalUser = User::factory()->withRole('hospital_admin')->create();

        $response = $this->actingAs($hospitalUser)->get(route('dashboard.hospital'));

        $response->assertOk()
            ->assertSee('Lengkapi Data Fasilitas Rumah Sakit')
            ->assertSee('Simpan & Aktifkan Dashboard Rumah Sakit');
    }

    public function test_allows_hospital_admin_to_setup_and_create_their_hospital_profile(): void
    {
        $hospitalUser = User::factory()->withRole('hospital_admin')->create();

        $response = $this->actingAs($hospitalUser)->post(route('dashboard.hospital.setup'), [
            'name' => 'RS Hermina Jakarta',
            'address' => 'Jl. Jatinegara Barat No. 126',
            'emergency_phone' => '021-8191223',
            'ambulance_entrance' => 'Pintu Masuk Barat',
            'latitude' => -6.2230,
            'longitude' => 106.8640,
        ]);

        $response->assertRedirect(route('dashboard.hospital'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('hospitals', [
            'name' => 'RS Hermina Jakarta',
            'emergency_phone' => '021-8191223',
            'admin_user_id' => $hospitalUser->id,
        ]);
    }

    public function test_allows_hospital_admin_to_update_their_hospital_details(): void
    {
        $hospitalUser = User::factory()->withRole('hospital_admin')->create();
        $hospital = Hospital::factory()->create([
            'admin_user_id' => $hospitalUser->id,
            'name' => 'RS Awal',
            'emergency_phone' => '021-111111',
        ]);

        $response = $this->actingAs($hospitalUser)->put(route('dashboard.hospital.profile'), [
            'name' => 'RS Awal Terbarukan',
            'address' => 'Jl. Baru No. 1',
            'emergency_phone' => '021-999999',
            'ambulance_entrance' => 'Gerbang Baru',
            'latitude' => -6.2100,
            'longitude' => 106.8200,
        ]);

        $response->assertRedirect(route('dashboard.hospital'))
            ->assertSessionHas('success');

        $this->assertEquals('RS Awal Terbarukan', $hospital->fresh()->name);
        $this->assertEquals('021-999999', $hospital->fresh()->emergency_phone);
    }

    public function test_allows_hospital_admin_to_store_a_new_driver(): void
    {
        $hospital = Hospital::factory()->create();
        $hospitalUser = User::factory()->withRole('hospital_admin')->create();
        $hospital->update(['admin_user_id' => $hospitalUser->id]);

        $ambulance = Ambulance::factory()->create(['hospital_id' => $hospital->id]);

        $response = $this->actingAs($hospitalUser)->post(route('dashboard.hospital.drivers.store'), [
            'full_name' => 'Bambang Sudirman',
            'email' => 'bambang.driver@example.com',
            'phone' => '081299887766',
            'license_number' => 'SIM-B1-778899',
            'alamat' => 'Jl. Pengemudi No. 12',
            'password' => 'driver-password',
            'ambulance_id' => $ambulance->id,
        ]);

        $response->assertRedirect(route('dashboard.hospital').'#driver')
            ->assertSessionHas('success');

        $driverUser = User::where('email', 'bambang.driver@example.com')->first();
        $this->assertNotNull($driverUser);
        $this->assertTrue($driverUser->hasRole('driver'));

        $driverProfile = DriverProfile::where('user_id', $driverUser->id)->first();
        $this->assertNotNull($driverProfile);
        $this->assertEquals($hospital->id, $driverProfile->hospital_id);
        $this->assertEquals('SIM-B1-778899', $driverProfile->license_number);
        $this->assertEquals($driverProfile->id, $ambulance->fresh()->driver_profile_id);
    }

    public function test_allows_administrator_to_switch_hospital_context(): void
    {
        $admin = User::factory()->withRole('administrator')->create();
        Hospital::factory()->create(['name' => 'RS Sentra Medika']);
        $hospB = Hospital::factory()->create(['name' => 'RS Mitra Keluarga']);

        $response = $this->actingAs($admin)->get(route('dashboard.hospital', ['hospital_id' => $hospB->id]));

        $response->assertOk()
            ->assertSee('RS Mitra Keluarga')
            ->assertSee('Pilih RS:');
    }

    public function test_allows_hospital_admin_to_update_emergency_request_status(): void
    {
        $hospitalUser = User::factory()->withRole('hospital_admin')->create();
        $hospital = Hospital::factory()->create(['admin_user_id' => $hospitalUser->id]);

        $driverUser = User::factory()->withRole('driver')->create();
        $driver = DriverProfile::factory()->create([
            'user_id' => $driverUser->id,
            'hospital_id' => $hospital->id,
            'availability_status' => DriverAvailability::Busy->value,
        ]);

        $emergency = EmergencyRequest::factory()->create([
            'hospital_id' => $hospital->id,
            'driver_profile_id' => $driver->id,
            'status' => EmergencyStatus::EnRouteToHospital->value,
        ]);

        $response = $this->actingAs($hospitalUser)->patch(
            route('dashboard.hospital.requests.update-status', $emergency->id),
            ['status' => EmergencyStatus::Completed->value]
        );

        $response->assertRedirect(route('dashboard.hospital').'#permintaan')
            ->assertSessionHas('success');

        $freshEmergency = $emergency->fresh();
        $this->assertEquals(EmergencyStatus::Completed->value, $freshEmergency->status);
        $this->assertNotNull($freshEmergency->completed_at);
        $this->assertEquals(DriverAvailability::Online, $driver->fresh()->availability_status);
    }

    public function test_allows_hospital_admin_to_cancel_emergency_request_with_reason(): void
    {
        $hospitalUser = User::factory()->withRole('hospital_admin')->create();
        $hospital = Hospital::factory()->create(['admin_user_id' => $hospitalUser->id]);

        $driverUser = User::factory()->withRole('driver')->create();
        $driver = DriverProfile::factory()->create([
            'user_id' => $driverUser->id,
            'hospital_id' => $hospital->id,
            'availability_status' => DriverAvailability::Busy->value,
        ]);

        $emergency = EmergencyRequest::factory()->create([
            'hospital_id' => $hospital->id,
            'driver_profile_id' => $driver->id,
            'status' => EmergencyStatus::EnRouteToPickup->value,
        ]);

        $response = $this->actingAs($hospitalUser)->patch(
            route('dashboard.hospital.requests.update-status', $emergency->id),
            [
                'status' => EmergencyStatus::Cancelled->value,
                'cancel_reason' => 'Pasien dijemput keluarga',
            ]
        );

        $response->assertRedirect(route('dashboard.hospital').'#permintaan')
            ->assertSessionHas('success');

        $freshEmergency = $emergency->fresh();
        $this->assertEquals(EmergencyStatus::Cancelled->value, $freshEmergency->status);
        $this->assertNotNull($freshEmergency->cancelled_at);
        $this->assertStringContainsString('Pasien dijemput keluarga', (string) $freshEmergency->cancel_reason);
        $this->assertEquals(DriverAvailability::Online, $driver->fresh()->availability_status);
    }

    public function test_forbids_hospital_admin_from_updating_requests_of_another_hospital(): void
    {
        $hospitalUser = User::factory()->withRole('hospital_admin')->create();
        Hospital::factory()->create(['admin_user_id' => $hospitalUser->id]);

        $otherHospital = Hospital::factory()->create();
        $emergency = EmergencyRequest::factory()->create([
            'hospital_id' => $otherHospital->id,
            'status' => EmergencyStatus::EnRouteToHospital->value,
        ]);

        $response = $this->actingAs($hospitalUser)->patch(
            route('dashboard.hospital.requests.update-status', $emergency->id),
            ['status' => EmergencyStatus::Completed->value]
        );

        $response->assertForbidden();
    }

    public function test_allows_super_admin_to_update_emergency_request_status_of_any_hospital(): void
    {
        $admin = User::factory()->withRole('administrator')->create();
        $hospital = Hospital::factory()->create();

        $emergency = EmergencyRequest::factory()->create([
            'hospital_id' => $hospital->id,
            'status' => EmergencyStatus::EnRouteToHospital->value,
        ]);

        $response = $this->actingAs($admin)->patch(
            route('dashboard.hospital.requests.update-status', $emergency->id),
            ['status' => EmergencyStatus::Completed->value]
        );

        $response->assertRedirect(route('dashboard.hospital').'#permintaan')
            ->assertSessionHas('success');

        $this->assertEquals(EmergencyStatus::Completed->value, $emergency->fresh()->status);
    }
}

