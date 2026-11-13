<?php

use App\Enums\DriverAvailability;
use App\Enums\EmergencyStatus;
use App\Models\Ambulance;
use App\Models\EmergencyRequest;
use App\Models\Hospital;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Dispatch runs here, not in the apps' polling requests: offers time out and
| move on to the next drivers within a second, and polls stay read-only.
*/
Schedule::call(fn () => EmergencyRequest::redispatchWaiting())
    ->everySecond()
    ->name('redispatch-emergency-requests')
    ->withoutOverlapping();

Schedule::call(fn () => EmergencyRequest::closeAbandoned())
    ->hourly()
    ->name('close-abandoned-emergency-requests')
    ->withoutOverlapping();

Artisan::command('demo:emergency {--driver=driver@cariambulan.id : Email of the driver to place the call near}', function () {
    $driverUser = User::where('email', $this->option('driver'))->first();
    $driver = $driverUser?->driverProfile;

    if ($driver === null || $driver->current_latitude === null) {
        $this->error('Driver not found or has no location yet. Go online in the app first.');

        return 1;
    }

    if ($driver->availability_status !== DriverAvailability::Online->value) {
        $this->warn("Driver is {$driver->availability_status}; the call will wait until they are online.");
    }

    $reporter = User::where('email', 'pasien@cariambulan.id')->firstOrFail();

    // ~1.5 km south-west of the driver.
    $emergency = $reporter->emergencyRequests()->create([
        'request_code' => EmergencyRequest::generateRequestCode(),
        'is_for_self' => true,
        'emergency_type' => 'Henti Jantung',
        'severity_level' => 'high',
        'patient_notes' => 'Ibu saya sesak dan nyeri dada sejak 10 menit lalu. Kami tunggu di lobby.',
        'pickup_latitude' => $driver->current_latitude - 0.011,
        'pickup_longitude' => $driver->current_longitude - 0.007,
        'pickup_address' => 'Jl. Sudirman No. 21, Jakarta Pusat (Lobby Wisma 46)',
        'status' => EmergencyStatus::SearchingDriver->value,
        'request_at' => now(),
    ]);

    $offer = $emergency->offerToNextDriver();

    $this->info("Created {$emergency->request_code}.");
    $this->line($offer === null
        ? 'No dispatchable driver right now; it will be offered when one is online.'
        : "Offered to {$offer->driver->user->full_name} ({$offer->distance_km} km).");

    return 0;
})->purpose('Create a test emergency call near a driver');

Artisan::command('driver:assign-ambulance {email : Email of the driver} {plate : Plate number, e.g. "P 1234 AB"} {--type=Ambulans BLS : Vehicle type} {--hospital= : Hospital id or name, when the driver has none}', function () {
    $driver = User::where('email', $this->argument('email'))->first()?->driverProfile;

    if ($driver === null) {
        $this->error('No driver account with that email.');

        return 1;
    }

    // Every ambulance belongs to a hospital; drivers who signed up on the web
    // have none yet.
    $hospital = $driver->hospital;
    if ($this->option('hospital') !== null) {
        $hospital = Str::isUuid($this->option('hospital'))
            ? Hospital::find($this->option('hospital'))
            : Hospital::whereRaw('lower(name) like ?', ['%'.strtolower($this->option('hospital')).'%'])->first();
    }

    if ($hospital === null) {
        $this->error('Pick the hospital this ambulance belongs to with --hospital=<id or name>:');
        $this->table(['id', 'name'], Hospital::orderBy('name')->get(['id', 'name'])->toArray());

        return 1;
    }

    $plate = $this->argument('plate');
    $takenBy = Ambulance::where('plate_number', $plate)
        ->where('driver_profile_id', '!=', $driver->id)
        ->exists();

    if ($takenBy) {
        $this->error("Ambulance {$plate} already belongs to another driver.");

        return 1;
    }

    $driver->update(['hospital_id' => $driver->hospital_id ?? $hospital->id]);
    $driver->ambulance()->updateOrCreate([], [
        'plate_number' => $plate,
        'vehicle_type' => $this->option('type'),
        'is_active' => true,
        'hospital_id' => $hospital->id,
    ]);

    $this->info("{$driver->user->full_name} now drives {$plate} for {$hospital->name}. They can go online to receive calls.");

    return 0;
})->purpose('Link an ambulance to a driver so dispatch can offer them calls');
