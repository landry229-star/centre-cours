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
        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained()->restrictOnDelete();
            $table->foreignId('corrected_by')->constrained('users')->restrictOnDelete();
            $table->string('before_status', 10);
            $table->string('after_status', 10);
            $table->time('before_arrived_at')->nullable();
            $table->time('after_arrived_at')->nullable();
            $table->string('before_note', 255)->nullable();
            $table->string('after_note', 255)->nullable();
            $table->timestamp('corrected_at')->useCurrent();
            $table->timestamps();

            $table->index(['attendance_id', 'corrected_at']);
            $table->index('corrected_by');
        });

        DB::statement("ALTER TABLE attendance_corrections ADD CONSTRAINT attendance_corrections_before_status_check CHECK (before_status IN ('present', 'absent', 'late', 'excused'))");
        DB::statement("ALTER TABLE attendance_corrections ADD CONSTRAINT attendance_corrections_after_status_check CHECK (after_status IN ('present', 'absent', 'late', 'excused'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_corrections');
    }
};
