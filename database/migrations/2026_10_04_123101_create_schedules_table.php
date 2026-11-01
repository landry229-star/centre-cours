<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('classroom_id');
            $table->unsignedBigInteger('teaching_assignment_id');
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('day_of_week', 10);
            $table->time('starts_at');
            $table->time('ends_at');
            $table->timestamps();

            $table->foreign(['teaching_assignment_id', 'classroom_id'])
                ->references(['id', 'classroom_id'])
                ->on('teaching_assignments')
                ->restrictOnDelete();
            $table->index(['classroom_id', 'day_of_week', 'starts_at']);
            $table->index(['room_id', 'day_of_week', 'starts_at']);
            $table->index(['teaching_assignment_id', 'day_of_week', 'starts_at']);
        });

        DB::statement("ALTER TABLE schedules ADD CONSTRAINT schedules_day_check CHECK (day_of_week IN ('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'))");
        DB::statement('ALTER TABLE schedules ADD CONSTRAINT schedules_time_check CHECK (ends_at > starts_at)');
    }

    public function down(): void
    {
        if (DB::table('schedules')->exists()) {
            throw new RuntimeException('Schedules exist and cannot be rolled back without losing timetable data.');
        }

        Schema::dropIfExists('schedules');
    }
};
