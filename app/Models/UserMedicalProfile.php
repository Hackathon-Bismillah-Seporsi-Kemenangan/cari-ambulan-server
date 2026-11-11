<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'blood_type',
    'allergies',
    'medical_conditions',
    'birth_date',
    'gender',
    'weight_kg',
    'height_cm',
    'emergency_contact_name',
    'emergency_contact_relation',
    'emergency_contact_phone',
    'insurance',
    'share_on_sos',
    'user_id',
])]
class UserMedicalProfile extends Model
{
    /** @use HasFactory<> */
    use HasFactory;

    use HasUuids;

    protected function casts(): array
    {
        return [
            'allergies' => 'array',
            'medical_conditions' => 'array',
            'weight_kg' => 'float',
            'height_cm' => 'float',
            'share_on_sos' => 'boolean',
            'birth_date' => 'date',
        ];
    }

    public function setAllergiesAttribute(mixed $value): void
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $this->attributes['allergies'] = json_encode($decoded !== null ? (array) $decoded : array_values(array_filter([$value])));
        } elseif (is_array($value)) {
            $this->attributes['allergies'] = json_encode(array_values(array_filter($value)));
        } else {
            $this->attributes['allergies'] = null;
        }
    }

    public function setMedicalConditionsAttribute(mixed $value): void
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $this->attributes['medical_conditions'] = json_encode($decoded !== null ? (array) $decoded : array_values(array_filter([$value])));
        } elseif (is_array($value)) {
            $this->attributes['medical_conditions'] = json_encode(array_values(array_filter($value)));
        } else {
            $this->attributes['medical_conditions'] = null;
        }
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
