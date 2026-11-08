<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('license_number', 20);
            $table->string('availability_status', 20)->default('offline');
            $table->decimal('current_latitude', 10, 8)->nullable();
            $table->decimal('current_longitude', 11, 8)->nullable();
            $table->timestamp('location_updated_at')->nullable();
            $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignUuid('hospital_id')->nullable()->constrained('hospitals')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_profiles');
    }
};

