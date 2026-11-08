<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ambulances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('plate_number', 50);
            $table->string('vehicle_type', 50)->default('Ambulans ALS');
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('driver_profile_id')->nullable()->constrained('driver_profiles')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignUuid('hospital_id')->nullable()->constrained('hospitals')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ambulances');
    }
};

