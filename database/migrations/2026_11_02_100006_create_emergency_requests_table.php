<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emergency_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('request_code', 20)->unique();
            $table->boolean('is_for_self')->default(true);
            $table->string('emergency_type', 50)->default('Lainnya');
            $table->string('severity_level', 20)->default('high');
            $table->text('patient_notes')->nullable();
            $table->decimal('pickup_latitude', 10, 8);
            $table->decimal('pickup_longitude', 11, 8);
            $table->text('pickup_address');
            $table->string('status', 30)->default('searching_driver');
            $table->unsignedSmallInteger('search_radius_km')->default(3);
            $table->timestamp('radius_changed_at')->nullable();
            $table->timestamp('search_exhausted_at')->nullable();

            $table->foreignUuid('user_id')->constrained('users')->cascadeOnUpdate();
            $table->foreignUuid('driver_profile_id')->nullable()->constrained('driver_profiles')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignUuid('ambulance_id')->nullable()->constrained('ambulances')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignUuid('hospital_id')->nullable()->constrained('hospitals')->cascadeOnUpdate()->nullOnDelete();

            $table->timestamp('request_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('arrived_at_pickup_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emergency_requests');
    }
};

