<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Voice calls from the reporter to the driver during an active order.
     */
    public function up(): void
    {
        Schema::create('trip_calls', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('emergency_request_id')->constrained('emergency_requests')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignUuid('caller_id')->constrained('users')->cascadeOnUpdate();
            $table->string('room_name')->nullable()->unique();
            $table->string('status', 20);
            $table->timestamp('rang_at');
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['emergency_request_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_calls');
    }
};

