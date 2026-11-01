<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('matricule', 20)->unique();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('gender', 1);
            $table->string('phone', 30);
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('specialty', 150);
            $table->string('education_level', 20);
            $table->date('hired_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->string('photo_path')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['last_name', 'first_name']);
            $table->index('status');
        });

        DB::statement("ALTER TABLE teachers ADD CONSTRAINT teachers_gender_check CHECK (gender IN ('M', 'F'))");
        DB::statement("ALTER TABLE teachers ADD CONSTRAINT teachers_status_check CHECK (status IN ('active', 'inactive', 'on_leave'))");
        DB::statement("ALTER TABLE teachers ADD CONSTRAINT teachers_education_check CHECK (education_level IN ('bac', 'licence', 'master', 'doctorat', 'other'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('teachers');
    }
};
