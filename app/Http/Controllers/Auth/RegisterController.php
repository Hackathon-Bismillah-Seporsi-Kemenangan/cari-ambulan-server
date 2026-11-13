<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Hospital;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function showRegistrationForm(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:200'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9][0-9\s\-()]{6,18}$/'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'hospital_name' => ['required', 'string', 'max:255'],
            'hospital_address' => ['required', 'string', 'max:255'],
            'emergency_phone' => ['required', 'string', 'max:50', 'regex:/^\+?[0-9][0-9\s\-()]{4,40}$/'],
            'ambulance_entrance' => ['nullable', 'string', 'max:100'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'health_facilities' => ['nullable', 'array'],
            'health_facilities.*' => ['string', 'max:100', 'distinct'],
        ], [
            'phone.regex' => 'Nomor HP tidak valid. Contoh: 0812 3456 7890.',
            'emergency_phone.regex' => 'Nomor telepon IGD tidak valid. Contoh: 021 555 0119.',
            'latitude.required' => 'Tentukan lokasi IGD pada peta.',
            'longitude.required' => 'Tentukan lokasi IGD pada peta.',
            'full_name.required' => 'Nama penanggung jawab wajib diisi.',
            'email.required' => 'Email login wajib diisi.',
            'email.unique' => 'Email ini sudah terdaftar.',
            'phone.required' => 'Nomor HP wajib diisi.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'hospital_name.required' => 'Nama rumah sakit wajib diisi.',
            'hospital_address.required' => 'Alamat rumah sakit wajib diisi.',
            'emergency_phone.required' => 'Nomor darurat / hotline IGD wajib diisi.',
        ]);

        $user = DB::transaction(function () use ($validated): User {
            $user = User::create([
                'full_name' => $validated['full_name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'alamat' => $validated['hospital_address'],
                'password' => $validated['password'],
            ]);

            $hospitalRole = Role::firstOrCreate(['name' => 'hospital_admin']);
            $user->roles()->attach($hospitalRole->id);

            Hospital::create([
                'name' => $validated['hospital_name'],
                'address' => $validated['hospital_address'],
                'emergency_phone' => $validated['emergency_phone'],
                'ambulance_entrance' => $validated['ambulance_entrance'] ?? 'Pintu Masuk IGD',
                'latitude' => $validated['latitude'] ?? -6.2000,
                'longitude' => $validated['longitude'] ?? 106.8166,
                'health_facilities' => $validated['health_facilities'] ?? [],
                'admin_user_id' => $user->id,
            ]);

            return $user;
        });

        Auth::guard('web')->login($user);

        return redirect()
            ->route('dashboard.hospital')
            ->with('success', 'Selamat datang! Pendaftaran akun Mitra Rumah Sakit berhasil.');
    }
}

