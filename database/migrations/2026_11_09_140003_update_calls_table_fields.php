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
        if (Schema::hasTable('calls')) {
            Schema::table('calls', function (Blueprint $table) {
                if (Schema::hasColumn('calls', 'callee_id') && ! Schema::hasColumn('calls', 'receiver_id')) {
                    $table->renameColumn('callee_id', 'receiver_id');
                }
                if (! Schema::hasColumn('calls', 'end_reason')) {
                    $table->string('end_reason', 32)->nullable()->after('status');
                }
                if (! Schema::hasColumn('calls', 'answered_at')) {
                    $table->timestamp('answered_at')->nullable()->after('started_at');
                }
                if (Schema::hasColumn('calls', 'call_type')) {
                    $table->string('call_type', 32)->nullable()->change();
                }
                if (Schema::hasColumn('calls', 'duration_seconds')) {
                    $table->unsignedInteger('duration_seconds')->nullable()->change();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('calls')) {
            Schema::table('calls', function (Blueprint $table) {
                if (Schema::hasColumn('calls', 'receiver_id') && ! Schema::hasColumn('calls', 'callee_id')) {
                    $table->renameColumn('receiver_id', 'callee_id');
                }
                if (Schema::hasColumn('calls', 'end_reason')) {
                    $table->dropColumn('end_reason');
                }
                if (Schema::hasColumn('calls', 'answered_at')) {
                    $table->dropColumn('answered_at');
                }
            });
        }
    }
};
