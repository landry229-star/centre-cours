<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // « id » est déjà unique : cette contrainte redondante sert uniquement de CIBLE à la clé étrangère
        // composite des présences, qui garantit que l'inscription appartient bien à la classe de la séance.
        DB::statement('ALTER TABLE enrollments ADD CONSTRAINT enrollments_id_classroom_unique UNIQUE (id, classroom_id)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE enrollments DROP CONSTRAINT enrollments_id_classroom_unique');
    }
};
