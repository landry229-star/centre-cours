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
        Schema::table('teachers', function (Blueprint $table) {
            $table->date('employment_ended_at')->nullable();
            $table->string('emergency_contact_name', 150)->nullable();
            $table->string('emergency_contact_phone', 30)->nullable();
        });

        DB::statement('ALTER TABLE teachers ADD CONSTRAINT teachers_employment_dates_check CHECK (employment_ended_at IS NULL OR hired_at IS NULL OR employment_ended_at >= hired_at)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE teachers DROP CONSTRAINT IF EXISTS teachers_employment_dates_check');

        Schema::table('teachers', function (Blueprint $table) {
            $table->dropColumn(['employment_ended_at', 'emergency_contact_name', 'emergency_contact_phone']);
        });
    }
};
