<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    private Role $userRole;

    private Role $driverRole;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->adminRole = Role::where('name', 'administrator')->firstOrFail();
        $this->userRole = Role::where('name', 'user')->firstOrFail();
        $this->driverRole = Role::where('name', 'driver')->firstOrFail();

        $this->admin = User::factory()->create();
        $this->admin->roles()->attach($this->adminRole);
    }

    public function test_administrator_can_view_the_user_create_page(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.users.create'));

        $response->assertOk()
            ->assertSee('Tambah User')
            ->assertSee('Nama Lengkap')
            ->assertSee('Alamat Email')
            ->assertSee('Nomor Telepon')
            ->assertSee('Alamat')
            ->assertSee('Kata Sandi')
            ->assertSee($this->driverRole->name);
    }

    public function test_non_administrator_cannot_access_user_create_page(): void
    {
        $regularUser = User::factory()->create();
        $regularUser->roles()->attach($this->userRole);

        $this->get(route('admin.users.create'))->assertRedirect(route('login'));
        $this->actingAs($regularUser)->get(route('admin.users.create'))->assertForbidden();
    }

    public function test_administrator_can_create_a_new_user_with_assigned_roles(): void
    {
        $userData = [
            'full_name' => 'Budi Santoso',
            'email' => 'budi.santoso@example.com',
            'phone' => '081234567890',
            'alamat' => 'Jl. Merdeka No. 10, Jakarta Pusat',
            'password' => 'secret12345',
            'password_confirmation' => 'secret12345',
            'roles' => [$this->driverRole->id],
        ];

        $response = $this->actingAs($this->admin)->post(route('admin.users.store'), $userData);

        $response->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'full_name' => 'Budi Santoso',
            'email' => 'budi.santoso@example.com',
            'phone' => '081234567890',
            'alamat' => 'Jl. Merdeka No. 10, Jakarta Pusat',
        ]);

        $createdUser = User::where('email', 'budi.santoso@example.com')->first();
        $this->assertNotNull($createdUser);
        $this->assertTrue(Hash::check('secret12345', $createdUser->password));
        $this->assertContains($this->driverRole->id, $createdUser->roles->pluck('id')->all());
    }

    public function test_administrator_can_create_a_user_without_roles(): void
    {
        $userData = [
            'full_name' => 'Siti Rahma',
            'email' => 'siti.rahma@example.com',
            'phone' => '089876543210',
            'alamat' => 'Jl. Sudirman No. 45, Bandung',
            'password' => 'secret12345',
            'password_confirmation' => 'secret12345',
        ];

        $response = $this->actingAs($this->admin)->post(route('admin.users.store'), $userData);

        $response->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'email' => 'siti.rahma@example.com',
        ]);

        $createdUser = User::where('email', 'siti.rahma@example.com')->first();
        $this->assertEmpty($createdUser->roles);
    }

    public function test_user_creation_validates_required_fields(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.users.store'), []);

        $response->assertSessionHasErrors(['full_name', 'email', 'phone', 'alamat', 'password']);
    }

    public function test_user_creation_validates_email_uniqueness_and_format(): void
    {
        User::factory()->create(['email' => 'duplicate@example.com']);

        $response = $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'full_name' => 'Test User',
            'email' => 'duplicate@example.com',
            'phone' => '081111111111',
            'alamat' => 'Alamat test',
            'password' => 'secret12345',
            'password_confirmation' => 'secret12345',
        ]);

        $response->assertSessionHasErrors('email');

        $invalidEmailResponse = $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'email' => 'not-an-email',
        ]);

        $invalidEmailResponse->assertSessionHasErrors('email');
    }

    public function test_user_creation_validates_phone_uniqueness(): void
    {
        User::factory()->create(['phone' => '081234567890']);

        $response = $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'full_name' => 'Test User',
            'email' => 'unique@example.com',
            'phone' => '081234567890',
            'alamat' => 'Alamat test',
            'password' => 'secret12345',
            'password_confirmation' => 'secret12345',
        ]);

        $response->assertSessionHasErrors('phone');
    }

    public function test_user_creation_validates_password_confirmation_and_minimum_length(): void
    {
        $shortPasswordResponse = $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'full_name' => 'Test User',
            'email' => 'unique@example.com',
            'phone' => '081234567890',
            'alamat' => 'Alamat test',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $shortPasswordResponse->assertSessionHasErrors('password');

        $mismatchedResponse = $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'full_name' => 'Test User',
            'email' => 'unique@example.com',
            'phone' => '081234567890',
            'alamat' => 'Alamat test',
            'password' => 'secret12345',
            'password_confirmation' => 'different12345',
        ]);

        $mismatchedResponse->assertSessionHasErrors('password');
    }

    public function test_administrator_can_assign_a_single_role_using_role_id(): void
    {
        $userData = [
            'full_name' => 'Ahmad Driver',
            'email' => 'ahmad.driver@example.com',
            'phone' => '087712345678',
            'alamat' => 'Jl. Tebet Raya No. 5',
            'password' => 'secret12345',
            'password_confirmation' => 'secret12345',
            'role_id' => $this->driverRole->id,
        ];

        $response = $this->actingAs($this->admin)->post(route('admin.users.store'), $userData);

        $response->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success');

        $user = User::where('email', 'ahmad.driver@example.com')->first();
        $this->assertCount(1, $user->roles);
        $this->assertEquals($this->driverRole->id, $user->roles->first()->id);
    }

    public function test_updating_user_role_maintains_one_user_one_role(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach($this->userRole);
        $this->assertCount(1, $user->roles);
        $this->assertEquals('user', $user->roles->first()->name);

        // Update to driver role
        $response = $this->actingAs($this->admin)->put(route('admin.users.update', $user), [
            'role_id' => $this->driverRole->id,
        ]);

        $response->assertRedirect(route('admin.users.show', $user))
            ->assertSessionHas('success');

        $user->refresh();
        $this->assertCount(1, $user->roles);
        $this->assertEquals('driver', $user->roles->first()->name);

        // Update to administrator role
        $response2 = $this->actingAs($this->admin)->put(route('admin.users.update', $user), [
            'role_id' => $this->adminRole->id,
        ]);

        $response2->assertRedirect(route('admin.users.show', $user));

        $user->refresh();
        $this->assertCount(1, $user->roles);
        $this->assertEquals('administrator', $user->roles->first()->name);
    }

    public function test_user_index_page_renders_with_sidebar_navigation_and_role_filters(): void
    {
        $driverUser = User::factory()->create(['full_name' => 'Driver Jono']);
        $driverUser->roles()->attach($this->driverRole);

        $regularUser = User::factory()->create(['full_name' => 'Pasien Budi']);
        $regularUser->roles()->attach($this->userRole);

        $response = $this->actingAs($this->admin)->get(route('admin.users.index'));

        $response->assertOk()
            ->assertSee('Direktori Pengguna Sistem')
            ->assertSee('Pusat Komando')
            ->assertSee('Manajemen User')
            ->assertSee('Driver Jono')
            ->assertSee('Pasien Budi');
    }

    public function test_user_index_page_filters_users_by_role_and_search_term(): void
    {
        $driverUser = User::factory()->create(['full_name' => 'Driver Khusus']);
        $driverUser->roles()->attach($this->driverRole);

        $regularUser = User::factory()->create(['full_name' => 'Warga Santai']);
        $regularUser->roles()->attach($this->userRole);

        // Filter by driver role
        $response = $this->actingAs($this->admin)->get(route('admin.users.index', ['role' => 'driver']));
        $response->assertOk()
            ->assertSee('Driver Khusus')
            ->assertDontSee('Warga Santai');

        // Filter by search query
        $searchResponse = $this->actingAs($this->admin)->get(route('admin.users.index', ['search' => 'Santai']));
        $searchResponse->assertOk()
            ->assertSee('Warga Santai')
            ->assertDontSee('Driver Khusus');
    }
}
