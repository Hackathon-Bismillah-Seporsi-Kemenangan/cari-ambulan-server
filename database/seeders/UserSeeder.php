<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Super Administrator
        $admin = User::firstOrCreate(
            ['email' => 'admin@cariambulan.id'],
            [
                'full_name' => 'Super Administrator',
                'phone' => '08000000000',
                'alamat' => 'Kantor Pusat CariAmbulan',
                'password' => Hash::make('Admin@123'),
            ]
        );
        $adminRole = Role::where('name', 'administrator')->first();
        if ($adminRole && ! $admin->roles->contains($adminRole)) {
            $admin->roles()->attach($adminRole);
        }

        // 2. Demo Driver
        $driver = User::firstOrCreate(
            ['email' => 'driver@cariambulan.id'],
            [
                'full_name' => 'Budi Santoso',
                'phone' => '081200001199',
                'alamat' => 'Jakarta Pusat',
                'password' => Hash::make('Driver@123'),
            ]
        );
        $driverRole = Role::where('name', 'driver')->first();
        if ($driverRole && ! $driver->roles->contains($driverRole)) {
            $driver->roles()->attach($driverRole);
        }

        // 3. Demo Patient / Civilian User
        $patient = User::firstOrCreate(
            ['email' => 'pasien@cariambulan.id'],
            [
                'full_name' => 'Siti Rahmawati',
                'phone' => '081234567890',
                'alamat' => 'Jl. Sudirman No. 21, Jakarta Pusat',
                'password' => Hash::make('Pasien@123'),
            ]
        );
        $userRole = Role::where('name', 'user')->first();
        if ($userRole && ! $patient->roles->contains($userRole)) {
            $patient->roles()->attach($userRole);
        }
    }
}

