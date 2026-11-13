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
        if (! Schema::hasColumn('hospitals', 'admin_user_id')) {
            Schema::table('hospitals', function (Blueprint $table): void {
                $table->foreignUuid('admin_user_id')
                    ->nullable()
                    ->unique()
                    ->after('id')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('hospitals', 'admin_user_id')) {
            Schema::table('hospitals', function (Blueprint $table): void {
                $table->dropForeign(['admin_user_id']);
                $table->dropUnique(['admin_user_id']);
                $table->dropColumn('admin_user_id');
            });
        }
    }
};

