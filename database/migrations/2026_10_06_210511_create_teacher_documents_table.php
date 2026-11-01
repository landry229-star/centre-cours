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
        Schema::create('teacher_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained()->restrictOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('type', 30);
            $table->date('document_date')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('original_filename', 255);
            $table->string('path');
            $table->timestamps();

            $table->index(['teacher_id', 'created_at']);
        });

        DB::statement("ALTER TABLE teacher_documents ADD CONSTRAINT teacher_documents_type_check CHECK (type IN ('contract', 'diploma', 'identity', 'certificate', 'other'))");
        DB::statement('ALTER TABLE teacher_documents ADD CONSTRAINT teacher_documents_dates_check CHECK (expires_at IS NULL OR document_date IS NULL OR expires_at >= document_date)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_documents');
    }
};
