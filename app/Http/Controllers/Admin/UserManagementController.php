<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DriverAvailability;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Models\Hospital;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    /**
     * List all users with their roles.
     */
    public function index(Request $request): View
    {
        $search = $request->string('search')->toString();
        $selectedRole = $request->string('role')->toString();

        $users = User::with(['roles', 'driverProfile.ambulance', 'driverProfile.hospital'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($selectedRole !== '', function ($query) use ($selectedRole) {
                $query->whereHas('roles', function ($q) use ($selectedRole) {
                    $q->where('name', $selectedRole);
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $roles = Role::withCount('users')->orderBy('name')->get();
        $totalUsers = User::count();

        return view('admin.users.index', compact('users', 'roles', 'search', 'selectedRole', 'totalUsers'));
    }

    /**
     * Show the form for creating a new user.
     */
    public function create(): View
    {
        $roles = Role::orderBy('name')->get();
        $hospitals = Hospital::orderBy('name')->get();

        return view('admin.users.create', compact('roles', 'hospitals'));
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'full_name' => $validated['full_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'alamat' => $validated['alamat'],
            'password' => $validated['password'],
        ]);

        $rolesInput = $request->input('roles');
        $roleId = $validated['role_id'] ?? (is_array($rolesInput) ? ($rolesInput[0] ?? null) : $rolesInput);

        if (! empty($roleId)) {
            $user->roles()->sync([$roleId]);
            $role = Role::find($roleId);
            if ($role && $role->name === 'driver') {
                $user->driverProfile()->create([
                    'license_number' => $request->input('license_number') ?: 'SIM-'.strtoupper(Str::random(6)),
                    'hospital_id' => $request->input('hospital_id') ?: null,
                    'availability_status' => DriverAvailability::Offline->value,
                ]);
            }
        }

        return redirect()
            ->route('admin.users.index')
            ->with('success', "User {$user->full_name} berhasil ditambahkan.");
    }

    /**
     * Show a single user's detail and role assignments.
     */
    public function show(User $user): View
    {
        $user->load(['roles', 'medicalProfile', 'driverProfile.ambulance', 'emergencyRequests' => function ($query) {
            $query->latest()->limit(5);
        }]);

        $allRoles = Role::orderBy('name')->get();

        return view('admin.users.show', compact('user', 'allRoles'));
    }

    /**
     * Update the role assigned to a user (1 user = 1 role).
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'role_id' => ['nullable', 'uuid', 'exists:roles,id'],
            'roles' => ['nullable'],
            'roles.*' => ['uuid', 'exists:roles,id'],
        ]);

        $rolesInput = $request->input('roles');
        $roleId = $validated['role_id'] ?? (is_array($rolesInput) ? ($rolesInput[0] ?? null) : $rolesInput);

        $user->roles()->sync($roleId ? [$roleId] : []);

        $roleName = $user->roles()->first()?->name ?? 'tanpa role';

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', "Role untuk {$user->full_name} berhasil diperbarui menjadi '{$roleName}'.");
    }

    /**
     * Remove a user from the system.
     */
    public function destroy(User $user): RedirectResponse
    {
        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', "User {$user->full_name} berhasil dihapus.");
    }
}

