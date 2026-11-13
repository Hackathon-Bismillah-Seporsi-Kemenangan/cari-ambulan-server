<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\DriverAvailability;
use App\Enums\EmergencyStatus;
use App\Http\Controllers\Controller;
use App\Models\Ambulance;
use App\Models\DriverProfile;
use App\Models\EmergencyRequest;
use App\Models\Hospital;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HospitalDashboardController extends Controller
{
    /**
     * Show the hospital admin dashboard for their own hospital's operations.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $selectedHospitalId = $request->query('hospital_id');
        $hospital = null;

        // Super administrator is allowed to pick/switch any hospital
        if ($user?->hasRole('administrator')) {
            if ($selectedHospitalId) {
                $hospital = Hospital::find($selectedHospitalId);
            }
            if (! $hospital) {
                $hospital = Hospital::first();
            }
        } else {
            // Hospital admin is strictly restricted to their own assigned hospital
            if ($user) {
                $hospital = Hospital::where('admin_user_id', $user->id)->first();
            }

            if (! $hospital && $user) {
                $hospital = Hospital::whereHas('driverProfiles', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })->first();
            }
        }

        $allHospitals = Hospital::orderBy('name')->get();

        $totalDrivers = 0;
        $totalAmbulances = 0;
        $activeAmbulances = 0;
        $busyAmbulances = 0;
        $recentRequests = collect();
        $availableDrivers = collect();
        $ambulances = collect();
        $drivers = collect();
        $totalRequests = 0;
        $completedRequests = 0;

        if ($hospital) {
            $totalDrivers = DriverProfile::where('hospital_id', $hospital->id)->count();
            $totalAmbulances = Ambulance::where('hospital_id', $hospital->id)->count();
            $activeAmbulances = Ambulance::where('hospital_id', $hospital->id)
                ->where('is_active', true)
                ->count();
            $busyAmbulances = Ambulance::where('hospital_id', $hospital->id)
                ->where('is_active', false)
                ->count();

            $ambulances = Ambulance::where('hospital_id', $hospital->id)
                ->with('driverProfile.user')
                ->latest()
                ->get();

            $drivers = DriverProfile::where('hospital_id', $hospital->id)
                ->with(['user', 'ambulance'])
                ->get();

            $recentRequests = EmergencyRequest::where(function ($query) use ($hospital) {
                $query->where('hospital_id', $hospital->id)
                    ->orWhereHas('driverProfile', fn ($q) => $q->where('hospital_id', $hospital->id));
            })
                ->with(['user', 'driverProfile.user', 'ambulance'])
                ->latest()
                ->limit(25)
                ->get();

            $availableDrivers = DriverProfile::where('hospital_id', $hospital->id)
                ->where('availability_status', DriverAvailability::Online->value)
                ->with(['user', 'ambulance'])
                ->get();

            $totalRequests = EmergencyRequest::where(function ($query) use ($hospital) {
                $query->where('hospital_id', $hospital->id)
                    ->orWhereHas('driverProfile', fn ($q) => $q->where('hospital_id', $hospital->id));
            })->count();
            $completedRequests = EmergencyRequest::where(function ($query) use ($hospital) {
                $query->where('hospital_id', $hospital->id)
                    ->orWhereHas('driverProfile', fn ($q) => $q->where('hospital_id', $hospital->id));
            })
                ->where('status', 'completed')
                ->count();
        }

        return view('dashboard.hospital', compact(
            'user',
            'hospital',
            'allHospitals',
            'totalDrivers',
            'totalAmbulances',
            'activeAmbulances',
            'busyAmbulances',
            'ambulances',
            'drivers',
            'recentRequests',
            'availableDrivers',
            'totalRequests',
            'completedRequests',
        ));
    }

    /**
     * Create hospital profile for the logged in hospital admin.
     */
    public function storeHospital(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'emergency_phone' => ['required', 'string', 'max:50'],
            'ambulance_entrance' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'health_facilities' => ['nullable', 'array'],
            'health_facilities.*' => ['string', 'max:100', 'distinct'],
        ], [
            'name.required' => 'Nama rumah sakit wajib diisi.',
            'address.required' => 'Alamat rumah sakit wajib diisi.',
            'emergency_phone.required' => 'Nomor darurat / hotline IGD wajib diisi.',
        ]);

        $user = $request->user();

        // Check if hospital already exists for this admin
        $existing = Hospital::where('admin_user_id', $user->id)->first();
        if ($existing) {
            $existing->update([
                'name' => $validated['name'],
                'address' => $validated['address'],
                'emergency_phone' => $validated['emergency_phone'],
                'ambulance_entrance' => $validated['ambulance_entrance'] ?? $existing->ambulance_entrance,
                'latitude' => $validated['latitude'] ?? $existing->latitude,
                'longitude' => $validated['longitude'] ?? $existing->longitude,
                'health_facilities' => $validated['health_facilities'] ?? $existing->health_facilities,
            ]);

            return redirect()->route('dashboard.hospital')
                ->with('success', 'Profil fasilitas rumah sakit berhasil diperbarui!');
        }

        Hospital::create([
            'name' => $validated['name'],
            'address' => $validated['address'],
            'emergency_phone' => $validated['emergency_phone'],
            'ambulance_entrance' => $validated['ambulance_entrance'] ?? 'Pintu Masuk IGD',
            'latitude' => $validated['latitude'] ?? -6.2000,
            'longitude' => $validated['longitude'] ?? 106.8166,
            'health_facilities' => $validated['health_facilities'] ?? [],
            'admin_user_id' => $user->id,
        ]);

        return redirect()->route('dashboard.hospital')
            ->with('success', 'Profil rumah sakit berhasil didaftarkan dan diaktifkan!');
    }

    /**
     * Update hospital profile.
     */
    public function updateHospital(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'hospital_id' => ['nullable', 'uuid', 'exists:hospitals,id'],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'emergency_phone' => ['required', 'string', 'max:50'],
            'ambulance_entrance' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'health_facilities' => ['nullable', 'array'],
            'health_facilities.*' => ['string', 'max:100', 'distinct'],
        ], [
            'name.required' => 'Nama rumah sakit wajib diisi.',
            'address.required' => 'Alamat rumah sakit wajib diisi.',
            'emergency_phone.required' => 'Nomor darurat / hotline IGD wajib diisi.',
        ]);

        $user = $request->user();
        $hospital = null;

        if ($user->hasRole('administrator') && ! empty($validated['hospital_id'])) {
            $hospital = Hospital::find($validated['hospital_id']);
        } else {
            $hospital = Hospital::where('admin_user_id', $user->id)->first();
        }

        if (! $hospital) {
            return redirect()->route('dashboard.hospital')
                ->withErrors(['error' => 'Data rumah sakit tidak ditemukan.']);
        }

        $hospital->update([
            'name' => $validated['name'],
            'address' => $validated['address'],
            'emergency_phone' => $validated['emergency_phone'],
            'ambulance_entrance' => $validated['ambulance_entrance'] ?? $hospital->ambulance_entrance,
            'latitude' => $validated['latitude'] ?? $hospital->latitude,
            'longitude' => $validated['longitude'] ?? $hospital->longitude,
            'health_facilities' => $validated['health_facilities'] ?? [],
        ]);

        return redirect()->route('dashboard.hospital')
            ->with('success', 'Informasi rumah sakit berhasil diperbarui!');
    }

    /**
     * Store a newly created driver profile under the hospital.
     */
    public function storeDriver(Request $request): RedirectResponse
    {
        $user = $request->user();
        $hospital = null;

        if ($user->hasRole('administrator') && $request->filled('hospital_id')) {
            $hospital = Hospital::find($request->input('hospital_id'));
        } else {
            $hospital = Hospital::where('admin_user_id', $user->id)->first();
        }

        if (! $hospital) {
            return redirect()->route('dashboard.hospital')
                ->withErrors(['error' => 'Data rumah sakit tidak ditemukan.']);
        }

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:200'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],
            'license_number' => ['required', 'string', 'max:20'],
            'alamat' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
            'ambulance_id' => ['nullable', 'uuid', 'exists:ambulances,id'],
        ], [
            'full_name.required' => 'Nama driver wajib diisi.',
            'email.required' => 'Email driver wajib diisi.',
            'email.unique' => 'Email ini sudah terdaftar.',
            'phone.required' => 'Nomor HP driver wajib diisi.',
            'phone.unique' => 'Nomor HP ini sudah digunakan.',
            'license_number.required' => 'Nomor SIM driver wajib diisi.',
            'password.required' => 'Kata sandi akun driver wajib diisi.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
        ]);

        DB::transaction(function () use ($validated, $hospital): void {
            $driverUser = User::create([
                'full_name' => $validated['full_name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'alamat' => $validated['alamat'] ?: $hospital->address,
                'password' => $validated['password'],
            ]);

            $driverRole = Role::firstOrCreate(['name' => 'driver']);
            $driverUser->roles()->attach($driverRole->id);

            $driverProfile = DriverProfile::create([
                'user_id' => $driverUser->id,
                'hospital_id' => $hospital->id,
                'license_number' => $validated['license_number'],
                'availability_status' => DriverAvailability::Offline->value,
            ]);

            if (! empty($validated['ambulance_id'])) {
                Ambulance::where('id', $validated['ambulance_id'])
                    ->where('hospital_id', $hospital->id)
                    ->update(['driver_profile_id' => $driverProfile->id]);
            }
        });

        return redirect()->to(route('dashboard.hospital').'#driver')
            ->with('success', "Driver {$validated['full_name']} berhasil didaftarkan ke tim driver rumah sakit.");
    }

    /**
     * Update the status of an emergency request (e.g. when stuck).
     */
    public function updateRequestStatus(Request $request, EmergencyRequest $emergencyRequest): RedirectResponse
    {
        $user = $request->user();
        $hospital = null;

        if ($user->hasRole('administrator')) {
            $hospital = $emergencyRequest->hospital ?? Hospital::first();
        } else {
            $hospital = Hospital::where('admin_user_id', $user->id)->first();
            if (! $hospital) {
                $hospital = Hospital::whereHas('driverProfiles', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })->first();
            }

            if (! $hospital) {
                abort(403, 'Akun Anda tidak terhubung ke rumah sakit manapun.');
            }

            $isRelated = ($emergencyRequest->hospital_id === $hospital->id)
                || ($emergencyRequest->driverProfile?->hospital_id === $hospital->id);

            if (! $isRelated) {
                abort(403, 'Anda tidak berwenang mengelola antrean rumah sakit lain.');
            }
        }

        $validated = $request->validate([
            'status' => [
                'required',
                'string',
                Rule::in(array_map(fn (EmergencyStatus $s) => $s->value, EmergencyStatus::cases())),
            ],
            'cancel_reason' => ['nullable', 'string', 'max:255'],
            'admin_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $newStatus = $validated['status'];

        DB::transaction(function () use ($emergencyRequest, $newStatus, $validated, $hospital): void {
            $updates = [
                'status' => $newStatus,
            ];

            // If hospital_id is not yet set, link to this hospital
            if (! $emergencyRequest->hospital_id && $hospital) {
                $updates['hospital_id'] = $hospital->id;
            }

            if ($newStatus === EmergencyStatus::Completed->value) {
                $updates['completed_at'] = now();

                // Terminate any active calls if table exists
                if (Schema::hasTable('trip_calls') && method_exists($emergencyRequest, 'calls')) {
                    $emergencyRequest->calls()->live()->update([
                        'status' => 'ended',
                        'ended_at' => now(),
                    ]);
                }

                // Free the driver
                if ($emergencyRequest->driver_profile_id) {
                    DriverProfile::where('id', $emergencyRequest->driver_profile_id)
                        ->update(['availability_status' => DriverAvailability::Online->value]);
                }
            } elseif ($newStatus === EmergencyStatus::Cancelled->value) {
                $updates['cancelled_at'] = now();
                $reason = $validated['cancel_reason'] ?? 'Dibatalkan oleh petugas RS / Admin';
                if (! empty($validated['admin_notes'])) {
                    $reason .= ' (Catatan: '.$validated['admin_notes'].')';
                }
                $updates['cancel_reason'] = $reason;

                // Terminate any active calls if table exists
                if (Schema::hasTable('trip_calls') && method_exists($emergencyRequest, 'calls')) {
                    $emergencyRequest->calls()->live()->update([
                        'status' => 'ended',
                        'ended_at' => now(),
                    ]);
                }

                // Free the driver
                if ($emergencyRequest->driver_profile_id) {
                    DriverProfile::where('id', $emergencyRequest->driver_profile_id)
                        ->update(['availability_status' => DriverAvailability::Online->value]);
                }
            } elseif ($newStatus === EmergencyStatus::ArriveAtPickup->value) {
                if (! $emergencyRequest->arrived_at_pickup_at) {
                    $updates['arrived_at_pickup_at'] = now();
                }
            } elseif ($newStatus === EmergencyStatus::EnRouteToHospital->value) {
                if (! $emergencyRequest->picked_up_at) {
                    $updates['picked_up_at'] = now();
                }
            } elseif ($newStatus === EmergencyStatus::EnRouteToPickup->value) {
                if (! $emergencyRequest->accepted_at) {
                    $updates['accepted_at'] = now();
                }
            } elseif ($newStatus === EmergencyStatus::SearchingDriver->value) {
                // Admin manually resetting order to search for driver again
                if ($emergencyRequest->driver_profile_id) {
                    DriverProfile::where('id', $emergencyRequest->driver_profile_id)
                        ->update(['availability_status' => DriverAvailability::Online->value]);
                }
                $updates['driver_profile_id'] = null;
                $updates['ambulance_id'] = null;
                $updates['accepted_at'] = null;
                $updates['arrived_at_pickup_at'] = null;
                $updates['picked_up_at'] = null;
            }

            $emergencyRequest->update($updates);

            if ($newStatus === EmergencyStatus::Completed->value || $newStatus === EmergencyStatus::Cancelled->value) {
                EmergencyRequest::redispatchWaiting();
            } elseif ($newStatus === EmergencyStatus::SearchingDriver->value) {
                $emergencyRequest->offerToNextDriver();
            }
        });

        $statusLabels = [
            'searching_driver' => 'Mencari Driver',
            'driver_assigned' => 'Driver Ditugaskan',
            'en_route_to_pickup' => 'Menuju Lokasi Pasien',
            'arrive_at_pickup' => 'Tiba di Lokasi',
            'en_route_to_hospital' => 'Menuju RS / IGD',
            'completed' => 'Selesai di IGD',
            'cancelled' => 'Dibatalkan',
        ];

        $targetLabel = $statusLabels[$newStatus] ?? $newStatus;

        return redirect()->to(route('dashboard.hospital').'#permintaan')
            ->with('success', "Status antrean {$emergencyRequest->request_code} berhasil diubah menjadi '{$targetLabel}'.");
    }
}

