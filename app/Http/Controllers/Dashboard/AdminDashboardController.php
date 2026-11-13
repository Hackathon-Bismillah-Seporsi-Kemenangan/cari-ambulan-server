<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Ambulance;
use App\Models\DispatchLog;
use App\Models\EmergencyRequest;
use App\Models\Hospital;
use App\Models\Role;
use App\Models\User;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    /**
     * Show the administrator dashboard with system-wide statistics.
     */
    public function index(): View
    {
        $totalUsers = User::count();
        $totalHospitals = Hospital::count();
        $totalAmbulances = Ambulance::count();
        $totalEmergencyRequests = EmergencyRequest::count();

        $usersByRole = Role::withCount('users')->get();

        $requestsByStatus = EmergencyRequest::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $recentEmergencyRequests = EmergencyRequest::with(['user', 'hospital', 'driverProfile.user'])
            ->latest()
            ->limit(10)
            ->get();

        $activeAmbulances = Ambulance::where('is_active', true)->count();
        $busyAmbulances = Ambulance::where('is_active', false)->count();

        $recentDispatchLogs = DispatchLog::with(['emergencyRequest', 'driver.user'])
            ->latest()
            ->limit(5)
            ->get();

        $roles = Role::withCount('users')->orderBy('name')->get();
        $recentUsers = User::with('roles')->latest()->limit(6)->get();
        $hospitals = Hospital::withCount(['ambulances', 'driverProfiles', 'emergencyRequests'])->latest()->get();

        return view('dashboard.admin', compact(
            'totalUsers',
            'totalHospitals',
            'totalAmbulances',
            'totalEmergencyRequests',
            'usersByRole',
            'requestsByStatus',
            'recentEmergencyRequests',
            'activeAmbulances',
            'busyAmbulances',
            'recentDispatchLogs',
            'roles',
            'recentUsers',
            'hospitals',
        ));
    }
}

