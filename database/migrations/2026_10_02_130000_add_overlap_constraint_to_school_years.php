<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Contrainte d'EXCLUSION : deux années scolaires ne peuvent pas se chevaucher.
        // daterange(..., '[]') inclut les deux bornes ; && signifie « se chevauchent ».
        // Aucune extension nécessaire (btree_gist ne sert qu'au mélange colonnes scalaires + plages).
        DB::statement("ALTER TABLE school_years ADD CONSTRAINT school_years_no_overlap EXCLUDE USING gist (daterange(start_date, end_date, '[]') WITH &&)");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE school_years DROP CONSTRAINT IF EXISTS school_years_no_overlap');
    }
};
