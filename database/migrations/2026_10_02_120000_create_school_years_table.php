<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_years', function (Blueprint $table) {
            $table->id();
            $table->string('label', 20)->unique();      // « 2026-2027 »
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE school_years ADD CONSTRAINT school_years_dates_check CHECK (end_date > start_date)');

        // Index UNIQUE PARTIEL : au plus UNE ligne avec is_active = true, garanti par PostgreSQL.
        DB::statement('CREATE UNIQUE INDEX school_years_one_active_idx ON school_years (is_active) WHERE is_active');
    }

    public function down(): void
    {
        Schema::dropIfExists('school_years');
    }
};
