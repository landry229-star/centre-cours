<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            // restrictOnDelete : on ne supprime jamais un tuteur qui a des élèves.
            $table->foreignId('guardian_id')->constrained()->restrictOnDelete();
            $table->string('matricule', 20)->unique();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('gender', 1);
            $table->date('birth_date');
            $table->string('birth_place', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('address')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('guardian_relationship', 20);
            $table->string('emergency_contact_name', 150)->nullable();
            $table->string('emergency_contact_phone', 30)->nullable();
            $table->string('status', 20)->default('active');
            // Jeton aléatoire encodé dans le QR Code (jamais l'id ni le matricule).
            $table->string('qr_token', 64)->unique();
            $table->softDeletes();
            $table->timestamps();

            // PostgreSQL n'indexe pas automatiquement les clés étrangères.
            $table->index('guardian_id');
            $table->index('status');
            $table->index(['last_name', 'first_name']);
        });

        DB::statement("ALTER TABLE students ADD CONSTRAINT students_gender_check CHECK (gender IN ('M', 'F'))");
        DB::statement("ALTER TABLE students ADD CONSTRAINT students_status_check CHECK (status IN ('active', 'inactive', 'suspended'))");
        DB::statement("ALTER TABLE students ADD CONSTRAINT students_relationship_check CHECK (guardian_relationship IN ('father', 'mother', 'guardian', 'uncle', 'aunt', 'sibling', 'other'))");
    }

    public function down(): void
    {
        // Les contraintes CHECK disparaissent avec la table.
        Schema::dropIfExists('students');
    }
};
