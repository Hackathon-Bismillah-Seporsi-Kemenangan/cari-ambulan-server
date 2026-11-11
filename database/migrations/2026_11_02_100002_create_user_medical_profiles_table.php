<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_medical_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('blood_type', ['A', 'B', 'AB', 'O', 'unknown'])
                ->nullable();
            $table->text('allergies')->nullable()
                ->comment('Riwayat alergi apa saja');
            $table->text('medical_conditions')->nullable()
                ->comment('Riwayat sakit nya apa saja');
            $table->date('birth_date')->nullable();
            $table->string('gender', 20);
            $table->foreignUuid('user_id')->nullable()
                ->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_medical_profiles');
    }
};
