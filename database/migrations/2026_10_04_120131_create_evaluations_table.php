<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE teaching_assignments ADD CONSTRAINT teaching_assignments_id_classroom_unique UNIQUE (id, classroom_id)');

        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('classroom_id');
            $table->unsignedBigInteger('teaching_assignment_id');
            $table->foreignId('teacher_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('title', 120);
            $table->string('type', 20);
            $table->date('evaluation_date');
            $table->unsignedTinyInteger('coefficient')->default(1);
            $table->unsignedTinyInteger('subject_coefficient');
            $table->timestamps();

            $table->foreign(['teaching_assignment_id', 'classroom_id'])
                ->references(['id', 'classroom_id'])
                ->on('teaching_assignments')
                ->restrictOnDelete();
            $table->unique(['id', 'classroom_id']);
            $table->index(['classroom_id', 'evaluation_date']);
            $table->index(['teaching_assignment_id', 'evaluation_date']);
            $table->index('teacher_id');
        });

        DB::statement("ALTER TABLE evaluations ADD CONSTRAINT evaluations_type_check CHECK (type IN ('homework', 'quiz', 'composition', 'exam', 'project', 'other'))");
        DB::statement('ALTER TABLE evaluations ADD CONSTRAINT evaluations_coefficients_check CHECK (coefficient BETWEEN 1 AND 10 AND subject_coefficient BETWEEN 1 AND 10)');
    }

    public function down(): void
    {
        if (DB::table('evaluations')->exists()) {
            throw new RuntimeException('Evaluations exist and cannot be rolled back without losing academic history.');
        }

        Schema::dropIfExists('evaluations');
        DB::statement('ALTER TABLE teaching_assignments DROP CONSTRAINT teaching_assignments_id_classroom_unique');
    }
};
