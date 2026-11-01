<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('classroom_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('school_year_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('title', 150);
            $table->text('body');
            $table->string('audience', 10);
            $table->timestamp('published_at');
            $table->timestamps();

            $table->index(['classroom_id', 'school_year_id', 'published_at']);
        });

        DB::statement(
            "ALTER TABLE announcements ADD CONSTRAINT announcements_audience_check CHECK ((audience = 'all' AND classroom_id IS NULL AND school_year_id IS NULL) OR (audience = 'class' AND classroom_id IS NOT NULL AND school_year_id IS NOT NULL))"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('announcements')->exists()) {
            throw new RuntimeException('Announcements exist and cannot be rolled back without losing communication history.');
        }

        Schema::dropIfExists('announcements');
    }
};
