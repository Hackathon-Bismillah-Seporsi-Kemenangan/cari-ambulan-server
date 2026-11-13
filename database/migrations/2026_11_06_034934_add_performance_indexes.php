<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emergency_requests', function (Blueprint $table) {
            $table->index('status');
            $table->index('user_id');
            $table->index('driver_profile_id');
        });

        Schema::table('driver_profiles', function (Blueprint $table) {
            $table->index(['availability_status', 'location_updated_at']);
        });

        Schema::table('dispatch_logs', function (Blueprint $table) {
            $table->index(['status', 'offered_at']);
            $table->index('driver_id');
        });
    }

    public function down(): void
    {
        Schema::table('emergency_requests', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['driver_profile_id']);
        });

        Schema::table('driver_profiles', function (Blueprint $table) {
            $table->dropIndex(['availability_status', 'location_updated_at']);
        });

        Schema::table('dispatch_logs', function (Blueprint $table) {
            $table->dropIndex(['status', 'offered_at']);
            $table->dropIndex(['driver_id']);
        });
    }
};

