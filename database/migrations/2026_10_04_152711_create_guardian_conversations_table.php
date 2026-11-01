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
        Schema::create('guardian_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guardian_id')->unique()->constrained()->restrictOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('guardian_conversations')->exists()) {
            throw new RuntimeException('Guardian conversations exist and cannot be rolled back without losing communication history.');
        }

        Schema::dropIfExists('guardian_conversations');
    }
};
