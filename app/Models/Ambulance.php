<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['plate_number', 'vehicle_type', 'is_active', 'driver_profile_id', 'hospital_id'])]
class Ambulance extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function driverProfile(): BelongsTo
    {
        return $this->belongsTo(DriverProfile::class, 'driver_profile_id');
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class, 'hospital_id');
    }
}

