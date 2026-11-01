<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Une séance = un cours donné à une classe, une matière, un jour, à une heure.
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('classroom_id');
            $table->unsignedBigInteger('subject_id');
            // Copie de l'enseignant au moment de la séance : l'historique reste juste si l'affectation change.
            $table->foreignId('teacher_id')->constrained()->restrictOnDelete();
            $table->date('session_date');
            $table->time('starts_at');
            $table->time('ends_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            // La matière doit réellement être enseignée dans cette classe, et l'affectation ne peut plus être
            // supprimée tant qu'une séance existe (la base de données s'en charge, pas seulement le code).
            $table->foreign(['classroom_id', 'subject_id'])
                ->references(['classroom_id', 'subject_id'])
                ->on('teaching_assignments')
                ->restrictOnDelete();

            // Anti-doublon : une seule séance pour (classe, matière, jour, heure de début).
            $table->unique(['classroom_id', 'subject_id', 'session_date', 'starts_at'], 'attendance_sessions_slot_unique');
            // Cible de la clé composite (id, classroom_id) utilisée par la table attendances.
            $table->unique(['id', 'classroom_id'], 'attendance_sessions_id_classroom_unique');

            $table->index('session_date');
            $table->index(['teacher_id', 'session_date']);
        });

        DB::statement('ALTER TABLE attendance_sessions ADD CONSTRAINT attendance_sessions_times_check CHECK (ends_at IS NULL OR ends_at > starts_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_sessions');
    }
};
