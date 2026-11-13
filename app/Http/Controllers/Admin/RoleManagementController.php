<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoleManagementController extends Controller
{
    /**
     * List all roles with their user counts.
     */
    public function index(): View
    {
        $roles = Role::withCount('users')->get();

        return view('admin.roles.index', compact('roles'));
    }

    /**
     * Show the form for creating a new role.
     */
    public function create(): View
    {
        return view('admin.roles.create');
    }

    /**
     * Store a newly created role.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', 'unique:roles,name'],
        ]);

        Role::create($validated);

        return redirect()
            ->route('admin.roles.index')
            ->with('success', "Role '{$validated['name']}' berhasil dibuat.");
    }

    /**
     * Show users assigned to this role.
     */
    public function show(Role $role): View
    {
        $role->load(['users' => function ($query) {
            $query->latest()->limit(50);
        }]);

        return view('admin.roles.show', compact('role'));
    }

    /**
     * Show the form to edit a role's name.
     */
    public function edit(Role $role): View
    {
        return view('admin.roles.edit', compact('role'));
    }

    /**
     * Update a role's name.
     */
    public function update(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', 'unique:roles,name,'.$role->id],
        ]);

        $role->update($validated);

        return redirect()
            ->route('admin.roles.index')
            ->with('success', "Role berhasil diperbarui menjadi '{$validated['name']}'.");
    }

    /**
     * Delete a role. Detaches all users from it first.
     */
    public function destroy(Role $role): RedirectResponse
    {
        $roleName = $role->name;
        $role->users()->detach();
        $role->delete();

        return redirect()
            ->route('admin.roles.index')
            ->with('success', "Role '{$roleName}' berhasil dihapus.");
    }
}

