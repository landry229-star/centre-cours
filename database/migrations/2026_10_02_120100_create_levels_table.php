<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('levels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('cycle', 20);
            $table->unsignedSmallInteger('position')->unique(); // ordre d'affichage
            $table->timestamps();
        });

        DB::statement("ALTER TABLE levels ADD CONSTRAINT levels_cycle_check CHECK (cycle IN ('primary', 'college', 'lycee'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('levels');
    }
};
