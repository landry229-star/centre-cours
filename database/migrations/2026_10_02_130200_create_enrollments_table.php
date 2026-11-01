<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('classroom_id');
            // Redondant avec classrooms.school_year_id (pratique pour filtrer et pour l'unicité),
            // mais rendu COHÉRENT par la clé étrangère composite ci-dessous.
            $table->unsignedBigInteger('school_year_id');
            $table->date('enrolled_at');
            $table->date('ended_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            // La classe ET l'année doivent correspondre à une même ligne de classrooms :
            // impossible d'inscrire un élève « 2026-2027 » dans une classe de 2027-2028.
            $table->foreign(['classroom_id', 'school_year_id'])
                ->references(['id', 'school_year_id'])
                ->on('classrooms')
                ->restrictOnDelete();

            $table->index('student_id');
            $table->index('classroom_id');
            $table->index('school_year_id');
            $table->index('status');
        });

        DB::statement("ALTER TABLE enrollments ADD CONSTRAINT enrollments_status_check CHECK (status IN ('active', 'transferred', 'withdrawn', 'completed'))");
        // Une inscription « en cours » n'a pas de date de fin ; toutes les autres en ont une.
        DB::statement("ALTER TABLE enrollments ADD CONSTRAINT enrollments_ended_check CHECK ((status = 'active') = (ended_at IS NULL))");
        DB::statement('ALTER TABLE enrollments ADD CONSTRAINT enrollments_dates_check CHECK (ended_at IS NULL OR ended_at >= enrolled_at)');

        // Un élève n'a qu'UNE inscription en cours par année scolaire. L'historique (autres statuts) est illimité.
        DB::statement("CREATE UNIQUE INDEX enrollments_one_active_per_year ON enrollments (student_id, school_year_id) WHERE status = 'active'");
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
