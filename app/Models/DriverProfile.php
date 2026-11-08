<?php

namespace App\Models;

use App\Enums\DriverAvailability;
use App\Models\Concerns\CalculatesDistance;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'license_number',
    'availability_status',
    'current_latitude',
    'current_longitude',
    'location_updated_at',
    'user_id',
    'hospital_id',
])]
class DriverProfile extends Model
{
    use CalculatesDistance, HasFactory, HasUuids;

    public const LOCATION_FRESH_MINUTES = 10;

    protected $attributes = [
        'availability_status' => 'offline',
    ];

    protected function casts(): array
    {
        return [
            'availability_status' => DriverAvailability::class,
            'current_latitude' => 'float',
            'current_longitude' => 'float',
            'location_updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class, 'hospital_id');
    }

    public function ambulance(): HasOne
    {
        return $this->hasOne(Ambulance::class, 'driver_profile_id');
    }
}

