<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $password = config('app.admin_password');

        if (blank($password)) {
            // The demo password is public (README), so only local may use it.
            throw_unless(app()->isLocal(), RuntimeException::class, 'Set ADMIN_PASSWORD in .env before seeding.');
            $password = 'Admin@123';
        }

        $adminUser = User::firstOrCreate(
            ['email' => 'admin@cariambulan.id'],
            [
                'full_name' => 'Super Administrator',
                'phone' => '08000000000',
                'alamat' => 'Kantor Pusat CariAmbulan',
                'password' => Hash::make($password),
            ],
        );

        $adminRole = Role::where('name', 'administrator')->first();

        if ($adminRole && ! $adminUser->roles->contains($adminRole)) {
            $adminUser->roles()->attach($adminRole);
        }
    }
}

