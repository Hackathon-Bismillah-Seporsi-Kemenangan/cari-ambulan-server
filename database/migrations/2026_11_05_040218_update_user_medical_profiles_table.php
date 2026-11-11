<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE user_medical_profiles DROP CONSTRAINT IF EXISTS user_medical_profiles_blood_type_check');
            DB::statement("ALTER TABLE user_medical_profiles ALTER COLUMN allergies TYPE json USING (CASE WHEN allergies IS NULL OR allergies = '' THEN NULL ELSE to_json(allergies) END)");
            DB::statement("ALTER TABLE user_medical_profiles ALTER COLUMN medical_conditions TYPE json USING (CASE WHEN medical_conditions IS NULL OR medical_conditions = '' THEN NULL ELSE to_json(medical_conditions) END)");
        } else {
            Schema::table('user_medical_profiles', function (Blueprint $table) {
                $table->json('allergies')->nullable()->change();
                $table->json('medical_conditions')->nullable()->change();
            });
        }

        Schema::table('user_medical_profiles', function (Blueprint $table) {
            $table->string('blood_type', 10)->nullable()->change();
            $table->string('gender', 20)->nullable()->change();
            $table->decimal('weight_kg', 6, 2)->nullable()->after('gender');
            $table->decimal('height_cm', 6, 2)->nullable()->after('weight_kg');
            $table->string('emergency_contact_name', 100)->nullable()->after('height_cm');
            $table->string('emergency_contact_relation', 50)->nullable()->after('emergency_contact_name');
            $table->string('emergency_contact_phone', 20)->nullable()->after('emergency_contact_relation');
            $table->string('insurance', 100)->nullable()->after('emergency_contact_phone');
            $table->boolean('share_on_sos')->default(true)->after('insurance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_medical_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'weight_kg',
                'height_cm',
                'emergency_contact_name',
                'emergency_contact_relation',
                'emergency_contact_phone',
                'insurance',
                'share_on_sos',
            ]);
            $table->string('gender', 20)->nullable(false)->change();
            $table->text('allergies')->nullable()->change();
            $table->text('medical_conditions')->nullable()->change();
        });
    }
};
