<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Redondant avec la clé primaire, mais PostgreSQL EXIGE une contrainte d'unicité sur (id, school_year_id)
        // pour qu'une clé étrangère composite puisse la référencer (voir table enrollments).
        DB::statement('ALTER TABLE classrooms ADD CONSTRAINT classrooms_id_year_unique UNIQUE (id, school_year_id)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE classrooms DROP CONSTRAINT IF EXISTS classrooms_id_year_unique');
    }
};
