<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('evaluation_id');
            $table->unsignedBigInteger('enrollment_id');
            $table->unsignedBigInteger('classroom_id');
            $table->foreignId('entered_by')->constrained('users')->restrictOnDelete();
            $table->decimal('score', 5, 2)->nullable();
            $table->string('comment', 255)->nullable();
            $table->timestamps();

            $table->foreign(['evaluation_id', 'classroom_id'])
                ->references(['id', 'classroom_id'])
                ->on('evaluations')
                ->restrictOnDelete();
            $table->foreign(['enrollment_id', 'classroom_id'])
                ->references(['id', 'classroom_id'])
                ->on('enrollments')
                ->restrictOnDelete();
            $table->unique(['evaluation_id', 'enrollment_id']);
            $table->index(['enrollment_id', 'evaluation_id']);
            $table->index(['classroom_id', 'evaluation_id']);
        });

        DB::statement('ALTER TABLE grades ADD CONSTRAINT grades_score_check CHECK (score IS NULL OR score BETWEEN 0 AND 20)');
    }

    public function down(): void
    {
        if (DB::table('grades')->exists()) {
            throw new RuntimeException('Grades exist and cannot be rolled back without losing academic history.');
        }

        Schema::dropIfExists('grades');
    }
};
