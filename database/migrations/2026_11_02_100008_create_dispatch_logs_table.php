<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispatch_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('emergency_request_id')->constrained('emergency_requests')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignUuid('driver_id')->constrained('driver_profiles')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('status', 20)->default('offered');
            $table->decimal('distance_km', 5, 2);
            $table->timestamp('offered_at');
            $table->timestamp('responded_at')->nullable();
            $table->index(['emergency_request_id', 'driver_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatch_logs');
    }
};

