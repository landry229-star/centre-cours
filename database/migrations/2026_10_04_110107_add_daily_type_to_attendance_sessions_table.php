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
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->string('session_type', 10)->default('lesson');
            $table->unsignedBigInteger('subject_id')->nullable()->change();
            $table->unsignedBigInteger('teacher_id')->nullable()->change();
        });

        DB::statement("ALTER TABLE attendance_sessions ADD CONSTRAINT attendance_sessions_type_check CHECK ((session_type = 'lesson' AND subject_id IS NOT NULL AND teacher_id IS NOT NULL) OR (session_type = 'daily' AND subject_id IS NULL AND teacher_id IS NOT NULL AND starts_at = '00:00:00' AND ends_at IS NULL))");
        DB::statement("CREATE UNIQUE INDEX attendance_sessions_daily_unique ON attendance_sessions (classroom_id, session_date) WHERE session_type = 'daily'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('attendance_sessions')->where('session_type', 'daily')->exists()) {
            throw new RuntimeException('Daily attendance sessions exist and cannot be rolled back safely.');
        }

        DB::statement('DROP INDEX attendance_sessions_daily_unique');
        DB::statement('ALTER TABLE attendance_sessions DROP CONSTRAINT attendance_sessions_type_check');

        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropColumn('session_type');
            $table->unsignedBigInteger('subject_id')->nullable(false)->change();
            $table->unsignedBigInteger('teacher_id')->nullable(false)->change();
        });
    }
};
