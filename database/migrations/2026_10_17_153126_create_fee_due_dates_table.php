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
        Schema::create('fee_due_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_year_id')->constrained()->restrictOnDelete();
            $table->date('month');
            $table->date('due_date')->nullable();
            $table->timestamps();

            $table->unique(['school_year_id', 'student_id', 'month']);
        });

        Schema::create('fee_due_date_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_due_date_id')->constrained()->restrictOnDelete();
            $table->date('old_due_date')->nullable();
            $table->date('new_due_date')->nullable();
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['fee_due_date_id', 'created_at']);
        });

        DB::statement('ALTER TABLE fee_due_dates ADD CONSTRAINT fee_due_dates_month_first_day_check CHECK (EXTRACT(DAY FROM month) = 1)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_due_date_changes');
        Schema::dropIfExists('fee_due_dates');
    }
};
