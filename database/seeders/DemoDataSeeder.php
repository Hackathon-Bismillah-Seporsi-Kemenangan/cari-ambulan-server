<?php

namespace Database\Seeders;

use App\Enums\DriverAvailability;
use App\Enums\EmergencyStatus;
use App\Models\EmergencyRequest;
use App\Models\Hospital;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo data for the mobile apps & web dashboards: hospitals around Jakarta
 * Pusat, hospital admins, one driver with an ambulance, and one civilian with
 * a Medical ID. Runs locally and on the test server (SEED_DEMO_DATA); it only
 * adds what is missing.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Hospital Admins (1-to-1 with partner hospitals)
        $hospitalAdminMedika = $this->userWithRole('hospital_admin', [
            'email' => 'hospital@cariambulan.id',
            'full_name' => 'Admin RS Medika',
            'phone' => '081299990119',
            'alamat' => 'Jl. RS Medika No. 1, Jakarta Pusat',
            'password' => Hash::make('Hospital@123'),
        ]);

        $hospitalAdminSehat = $this->userWithRole('hospital_admin', [
            'email' => 'rs.sehat@cariambulan.id',
            'full_name' => 'Admin RS Sehat Sentosa',
            'phone' => '081288880119',
            'alamat' => 'Jl. Sehat No. 10, Jakarta Pusat',
            'password' => Hash::make('Hospital@123'),
        ]);

        // 2. Partner Hospitals with attached admin_user_id
        $hospitals = collect([
            [
                'name' => 'RS Medika',
                'latitude' => -6.2005,
                'longitude' => 106.8160,
                'ambulance_entrance' => 'Gerbang B',
                'admin_user_id' => $hospitalAdminMedika->id,
            ],
            [
                'name' => 'RS Sehat Sentosa',
                'latitude' => -6.1862,
                'longitude' => 106.8063,
                'ambulance_entrance' => 'Gerbang A',
                'admin_user_id' => $hospitalAdminSehat->id,
            ],
            [
                'name' => 'RS Bunda Kasih',
                'latitude' => -6.2297,
                'longitude' => 106.8296,
                'ambulance_entrance' => 'Pintu IGD Timur',
                'admin_user_id' => null,
            ],
        ])->map(fn (array $hospital) => Hospital::updateOrCreate(
            ['name' => $hospital['name']],
            [...$hospital, 'address' => 'Jakarta Pusat', 'emergency_phone' => '0215550119'],
        ));

        // 3. Driver user with assigned ambulance
        $driverUser = $this->userWithRole('driver', [
            'email' => 'driver@cariambulan.id',
            'full_name' => 'Budi Santoso',
            'phone' => '081200001199',
            'alamat' => 'Jakarta Pusat',
            'password' => Hash::make('Driver@123'),
        ]);

        $driver = $driverUser->driverProfile()->firstOrCreate([], [
            'license_number' => 'SIM-31750001',
            'availability_status' => DriverAvailability::Online->value,
            'hospital_id' => $hospitals->first()->id,
        ]);

        $driver->ambulance()->firstOrCreate([], [
            'plate_number' => 'B 1199 SGM',
            'vehicle_type' => 'Ambulans ALS',
            'is_active' => true,
            'hospital_id' => $hospitals->first()->id,
        ]);

        // 4. Civilian user / patient
        $patient = $this->userWithRole('user', [
            'email' => 'pasien@cariambulan.id',
            'full_name' => 'Siti Rahmawati',
            'phone' => '081234567890',
            'alamat' => 'Jl. Sudirman No. 21, Jakarta Pusat',
            'password' => Hash::make('Pasien@123'),
        ]);

        $patient->medicalProfile()->firstOrCreate([], [
            'blood_type' => 'O',
            'allergies' => ['Alergi penisilin'],
            'medical_conditions' => ['Hipertensi'],
            'birth_date' => '1968-04-12',
            'gender' => 'Perempuan',
        ]);

        // 5. Sample Emergency Requests for Hospital Admin Dashboard
        EmergencyRequest::firstOrCreate(
            ['request_code' => 'CA-MED001'],
            [
                'user_id' => $patient->id,
                'hospital_id' => $hospitals->first()->id,
                'driver_profile_id' => $driver->id,
                'ambulance_id' => $driver->ambulance->id,
                'emergency_type' => 'Sesak Nafas',
                'severity_level' => 'high',
                'patient_notes' => 'Pasien riwayat asma mendadak sesak, butuh tabung oksigen',
                'pickup_latitude' => -6.2088,
                'pickup_longitude' => 106.8222,
                'pickup_address' => 'Jl. Kebon Sirih No. 12, Jakarta Pusat',
                'status' => EmergencyStatus::DriverAssigned->value,
                'request_at' => now()->subMinutes(20),
                'accepted_at' => now()->subMinutes(18),
            ],
        );

        EmergencyRequest::firstOrCreate(
            ['request_code' => 'CA-MED002'],
            [
                'user_id' => $patient->id,
                'hospital_id' => $hospitals->first()->id,
                'driver_profile_id' => $driver->id,
                'ambulance_id' => $driver->ambulance->id,
                'emergency_type' => 'Kecelakaan',
                'severity_level' => 'high',
                'patient_notes' => 'Cedera kepala ringan pasca insiden lalu lintas',
                'pickup_latitude' => -6.2010,
                'pickup_longitude' => 106.8190,
                'pickup_address' => 'Jl. M.H. Thamrin No. 8, Jakarta Pusat',
                'status' => EmergencyStatus::Completed->value,
                'request_at' => now()->subHours(2),
                'accepted_at' => now()->subHours(2)->addMinutes(5),
                'completed_at' => now()->subHours(1),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function userWithRole(string $roleName, array $attributes): User
    {
        $user = User::firstOrCreate(['email' => $attributes['email']], $attributes);
        $user->roles()->syncWithoutDetaching([Role::firstOrCreate(['name' => $roleName])->id]);

        return $user;
    }
}

