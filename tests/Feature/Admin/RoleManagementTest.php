<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->withRole('administrator')->create();
        $this->regularUser = User::factory()->withRole('user')->create();
    }

    public function test_admin_can_view_roles_index(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.roles.index'));

        $response->assertOk()
            ->assertSee('Manajemen Role')
            ->assertSee('administrator');
    }

    public function test_admin_can_create_role(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.roles.store'), [
            'name' => 'dispatcher',
        ]);

        $response->assertRedirect(route('admin.roles.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('roles', ['name' => 'dispatcher']);
    }

    public function test_admin_can_update_role(): void
    {
        $role = Role::create(['name' => 'operator']);

        $response = $this->actingAs($this->admin)->put(route('admin.roles.update', $role), [
            'name' => 'head_operator',
        ]);

        $response->assertRedirect(route('admin.roles.index'))
            ->assertSessionHas('success');

        $this->assertEquals('head_operator', $role->fresh()->name);
    }

    public function test_admin_can_delete_role(): void
    {
        $role = Role::create(['name' => 'temp_role']);

        $response = $this->actingAs($this->admin)->delete(route('admin.roles.destroy', $role));

        $response->assertRedirect(route('admin.roles.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('roles', ['name' => 'temp_role']);
    }

    public function test_non_admin_cannot_access_roles(): void
    {
        $this->actingAs($this->regularUser)->get(route('admin.roles.index'))
            ->assertForbidden();
    }
}
