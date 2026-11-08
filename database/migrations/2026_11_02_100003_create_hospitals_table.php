<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hospitals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 200);
            $table->text('address');
            $table->string('emergency_phone', 20);
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('ambulance_entrance', 100)->nullable()
                ->comment('Lokasi pintu masuk ambulans, misal: Gerbang B');
            $table->foreignUuid('admin_user_id')
                ->nullable()
                ->unique()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospitals');
    }
};

