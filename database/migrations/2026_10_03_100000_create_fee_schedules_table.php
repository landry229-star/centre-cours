<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('level_id')->constrained()->restrictOnDelete();
            // FCFA : pas de décimales, donc un entier (jamais de float pour de l'argent).
            $table->unsignedInteger('monthly_amount');
            $table->timestamps();

            // Un seul tarif par niveau et par année.
            $table->unique(['school_year_id', 'level_id']);
        });

        DB::statement('ALTER TABLE fee_schedules ADD CONSTRAINT fee_schedules_amount_check CHECK (monthly_amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_schedules');
    }
};
