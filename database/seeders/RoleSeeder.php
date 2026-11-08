<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Canonical role names.
     *
     * @var array<string>
     */
    private array $roles = [
        'administrator',
        'user',
        'driver',
        'hospital_admin',
    ];

    public function run(): void
    {
        foreach ($this->roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
        }
    }
}

