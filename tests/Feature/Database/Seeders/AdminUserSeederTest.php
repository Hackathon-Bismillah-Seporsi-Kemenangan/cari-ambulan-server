<?php

namespace Tests\Feature\Database\Seeders;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class AdminUserSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_uses_admin_password_when_set(): void
    {
        config(['app.admin_password' => 'Str0ng-Secret!']);

        $this->seed(AdminUserSeeder::class);

        $admin = User::where('email', 'admin@cariambulan.id')->first();
        $this->assertNotNull($admin);
        $this->assertTrue(Hash::check('Str0ng-Secret!', $admin->password));
        $this->assertContains('administrator', $admin->roles->pluck('name')->all());
    }

    public function test_refuses_public_demo_password_outside_local(): void
    {
        config(['app.admin_password' => null]);
        $this->app->detectEnvironment(fn () => 'production');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Set ADMIN_PASSWORD in .env before seeding.');

        app(AdminUserSeeder::class)->run();
    }

    public function test_falls_back_to_demo_password_locally(): void
    {
        config(['app.admin_password' => null]);
        $this->app->detectEnvironment(fn () => 'local');

        $this->seed(AdminUserSeeder::class);

        $admin = User::where('email', 'admin@cariambulan.id')->first();
        $this->assertNotNull($admin);
        $this->assertTrue(Hash::check('Admin@123', $admin->password));
    }
}

