<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('attendance_session_id');
            // Redondant avec la séance, mais rendu COHÉRENT par les deux clés composites ci-dessous.
            $table->unsignedBigInteger('classroom_id');
            $table->unsignedBigInteger('enrollment_id');
            $table->string('status', 10);
            $table->time('arrived_at')->nullable();   // heure d'arrivée (retard, scan QR)
            $table->string('note', 255)->nullable();  // ex. motif de l'absence justifiée
            $table->foreignId('marked_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            // La ligne appartient à une séance DE CETTE CLASSE...
            $table->foreign(['attendance_session_id', 'classroom_id'])
                ->references(['id', 'classroom_id'])->on('attendance_sessions')->restrictOnDelete();
            // ... et concerne un élève inscrit DANS CETTE MÊME CLASSE.
            $table->foreign(['enrollment_id', 'classroom_id'])
                ->references(['id', 'classroom_id'])->on('enrollments')->restrictOnDelete();

            // Anti-doublon : un élève n'est pointé qu'UNE fois par séance.
            $table->unique(['attendance_session_id', 'enrollment_id']);
            $table->index('enrollment_id');
            $table->index('status');
        });

        DB::statement("ALTER TABLE attendances ADD CONSTRAINT attendances_status_check CHECK (status IN ('present', 'absent', 'late', 'excused'))");
        // Une heure d'arrivée n'a de sens que pour un élève présent ou en retard.
        DB::statement("ALTER TABLE attendances ADD CONSTRAINT attendances_arrived_check CHECK (arrived_at IS NULL OR status IN ('present', 'late'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
