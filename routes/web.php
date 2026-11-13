<?php

use App\Http\Controllers\Admin\RoleManagementController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Dashboard\AdminDashboardController;
use App\Http\Controllers\Dashboard\HospitalDashboardController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Authentication Routes (Guest / Auth)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
    Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->name('register.attempt');
});

Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Dashboard Routes — protected by auth + role middleware
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {

    // Redirect /dashboard to the correct role-specific dashboard (Admin or Hospital)
    Route::get('/dashboard', function () {
        /** @var User $user */
        $user = request()->user();
        $user->load('roles');

        if ($user->hasRole('administrator')) {
            return redirect()->route('dashboard.admin');
        }

        if ($user->hasRole('hospital_admin')) {
            return redirect()->route('dashboard.hospital');
        }

        // Driver and civilian roles are strictly mobile-only
        Auth::guard('web')->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login')->withErrors([
            'email' => 'Portal web ini khusus untuk Administrator dan Mitra Rumah Sakit. Driver ambulans dan pasien silakan mengakses melalui aplikasi mobile Cari Ambulan.',
        ]);
    })->name('dashboard');

    // --- Administrator dashboard ---
    Route::get('/dashboard/admin', [AdminDashboardController::class, 'index'])
        ->middleware('role:administrator')
        ->name('dashboard.admin');

    // --- Hospital admin dashboard & profile setup ---
    Route::get('/dashboard/hospital', [HospitalDashboardController::class, 'index'])
        ->middleware('role:hospital_admin,administrator')
        ->name('dashboard.hospital');
    Route::post('/dashboard/hospital/setup', [HospitalDashboardController::class, 'storeHospital'])
        ->middleware('role:hospital_admin,administrator')
        ->name('dashboard.hospital.setup');
    Route::put('/dashboard/hospital/profile', [HospitalDashboardController::class, 'updateHospital'])
        ->middleware('role:hospital_admin,administrator')
        ->name('dashboard.hospital.profile');
    Route::post('/dashboard/hospital/drivers', [HospitalDashboardController::class, 'storeDriver'])
        ->middleware('role:hospital_admin,administrator')
        ->name('dashboard.hospital.drivers.store');
    Route::patch('/dashboard/hospital/requests/{emergencyRequest}/status', [HospitalDashboardController::class, 'updateRequestStatus'])
        ->middleware('role:hospital_admin,administrator')
        ->name('dashboard.hospital.requests.update-status');

    /*
    |--------------------------------------------------------------------------
    | Admin Panel Routes — administrator only
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:administrator')->prefix('admin')->name('admin.')->group(function () {

        // User management
        Route::get('users', [UserManagementController::class, 'index'])->name('users.index');
        Route::get('users/create', [UserManagementController::class, 'create'])->name('users.create');
        Route::post('users', [UserManagementController::class, 'store'])->name('users.store');
        Route::get('users/{user}', [UserManagementController::class, 'show'])->name('users.show');
        Route::put('users/{user}', [UserManagementController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');

        // Role management (full CRUD)
        Route::resource('roles', RoleManagementController::class)->names([
            'index' => 'roles.index',
            'create' => 'roles.create',
            'store' => 'roles.store',
            'show' => 'roles.show',
            'edit' => 'roles.edit',
            'update' => 'roles.update',
            'destroy' => 'roles.destroy',
        ]);
    });
});
