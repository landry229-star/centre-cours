<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classrooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('level_id')->constrained()->restrictOnDelete();
            $table->string('name', 50);
            $table->unsignedSmallInteger('capacity');
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('head_teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->string('status', 20)->default('active');
            $table->softDeletes();
            $table->timestamps();

            $table->index('school_year_id');
            $table->index('level_id');
            $table->index('room_id');
            $table->index('head_teacher_id');
        });

        DB::statement('ALTER TABLE classrooms ADD CONSTRAINT classrooms_capacity_check CHECK (capacity > 0)');
        DB::statement("ALTER TABLE classrooms ADD CONSTRAINT classrooms_status_check CHECK (status IN ('active', 'closed'))");

        // Nom unique par année scolaire, en ignorant les classes supprimées logiquement
        // (sinon une classe archivée bloquerait à jamais la réutilisation de son nom).
        DB::statement('CREATE UNIQUE INDEX classrooms_year_name_unique ON classrooms (school_year_id, name) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('classrooms');
    }
};
