<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // « Qui enseigne QUELLE matière dans QUELLE classe » (voir Phase 3).
        Schema::create('teaching_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained()->restrictOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('coefficient')->default(1);
            $table->unsignedSmallInteger('weekly_hours')->nullable();
            $table->timestamps();

            // Une matière n'a qu'un enseignant par classe.
            $table->unique(['classroom_id', 'subject_id']);
            $table->index('teacher_id');
            $table->index('subject_id');
        });

        DB::statement('ALTER TABLE teaching_assignments ADD CONSTRAINT teaching_assignments_coefficient_check CHECK (coefficient BETWEEN 1 AND 10)');
        DB::statement('ALTER TABLE teaching_assignments ADD CONSTRAINT teaching_assignments_hours_check CHECK (weekly_hours IS NULL OR weekly_hours BETWEEN 1 AND 40)');
    }

    public function down(): void
    {
        Schema::dropIfExists('teaching_assignments');
    }
};
