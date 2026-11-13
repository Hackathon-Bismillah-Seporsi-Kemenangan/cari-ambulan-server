<?php

namespace Tests\Feature\Database\Seeders;

use App\Models\DriverProfile;
use App\Models\EmergencyRequest;
use App\Models\Hospital;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_seeds_hospital_admin_hospitals_driver_patient_and_requests(): void
    {
        $this->seed(DemoDataSeeder::class);

        $hospitalAdmin = User::where('email', 'hospital@cariambulan.id')->first();
        $this->assertNotNull($hospitalAdmin);
        $this->assertTrue(Hash::check('Hospital@123', $hospitalAdmin->password));
        $this->assertContains('hospital_admin', $hospitalAdmin->roles->pluck('name')->all());

        $this->assertGreaterThanOrEqual(3, Hospital::count());
        $medika = Hospital::where('name', 'RS Medika')->first();
        $this->assertEquals($hospitalAdmin->id, $medika->admin_user_id);

        $sehat = Hospital::where('name', 'RS Sehat Sentosa')->first();
        $sehatAdmin = User::where('email', 'rs.sehat@cariambulan.id')->first();
        $this->assertEquals($sehatAdmin->id, $sehat->admin_user_id);

        $driverUser = User::where('email', 'driver@cariambulan.id')->first();
        $this->assertNotNull($driverUser);
        $this->assertNotNull($driverUser->driverProfile);

        $this->assertTrue(EmergencyRequest::where('request_code', 'CA-MED001')->exists());
        $this->assertTrue(EmergencyRequest::where('request_code', 'CA-MED002')->exists());
    }

    public function test_allows_hospital_admin_to_access_hospital_dashboard_after_seeding(): void
    {
        $this->seed(DemoDataSeeder::class);

        $hospitalAdmin = User::where('email', 'hospital@cariambulan.id')->first();

        $response = $this->actingAs($hospitalAdmin)->get(route('dashboard.hospital'));

        $response->assertOk()
            ->assertSee('Admin RS Medika')
            ->assertSee('RS Medika')
            ->assertSee('CA-MED001')
            ->assertSee('Sesak Nafas')
            ->assertDontSee('Pilih RS:');

        $sehat = Hospital::where('name', 'RS Sehat Sentosa')->first();
        $tamperedResponse = $this->actingAs($hospitalAdmin)->get(route('dashboard.hospital', ['hospital_id' => $sehat->id]));
        $tamperedResponse->assertOk()
            ->assertSee('RS Medika')
            ->assertDontSee('RS Sehat Sentosa')
            ->assertDontSee('Pilih RS:');

        $sehatAdmin = User::where('email', 'rs.sehat@cariambulan.id')->first();
        $responseSehat = $this->actingAs($sehatAdmin)->get(route('dashboard.hospital'));
        $responseSehat->assertOk()
            ->assertSee('Admin RS Sehat Sentosa')
            ->assertSee('RS Sehat Sentosa')
            ->assertDontSee('Pilih RS:');
    }

    public function test_gives_existing_driver_without_ambulance_one(): void
    {
        $user = User::factory()->create([
            'email' => 'driver@cariambulan.id',
            'password' => Hash::make('TheirOwn@123'),
        ]);
        $user->roles()->attach(Role::firstOrCreate(['name' => 'driver']));
        $driver = DriverProfile::factory()->for($user)->create(['hospital_id' => null]);

        $this->seed(DemoDataSeeder::class);

        $ambulance = $driver->ambulance()->first();
        $this->assertNotNull($ambulance);
        $this->assertEquals('B 1199 SGM', $ambulance->plate_number);
        $this->assertNotNull($ambulance->hospital_id);
        $this->assertTrue(Hash::check('TheirOwn@123', $user->fresh()->password));
    }

    public function test_can_run_repeatedly_idempotently(): void
    {
        $this->seed(DemoDataSeeder::class);
        $this->seed(DemoDataSeeder::class);

        $driver = User::where('email', 'driver@cariambulan.id')->sole()->driverProfile;

        $this->assertEquals(1, User::where('email', 'pasien@cariambulan.id')->count());
        $this->assertEquals(1, DriverProfile::where('user_id', $driver->user_id)->count());
        $this->assertEquals(1, $driver->ambulance()->count());
        $this->assertEquals(0, DriverProfile::dispatchable()->count());
    }
}

