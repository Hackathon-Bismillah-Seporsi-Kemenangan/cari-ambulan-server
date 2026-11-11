<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserMedicalProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserMedicalProfile>
 */
class UserMedicalProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'blood_type' => fake()->randomElement(['A', 'B', 'AB', 'O']),
            'allergies' => fake()->optional()->randomElement(['Alergi penisilin', 'Alergi seafood']),
            'medical_conditions' => fake()->optional()->randomElement(['Hipertensi', 'Asma', 'Diabetes']),
            'birth_date' => fake()->date(),
            'gender' => fake()->randomElement(['Laki-laki', 'Perempuan']),
            'user_id' => User::factory(),
        ];
    }
}
