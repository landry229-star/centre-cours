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
        Schema::create('guardian_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guardian_conversation_id')
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('sender_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['guardian_conversation_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('guardian_messages')->exists()) {
            throw new RuntimeException('Guardian messages exist and cannot be rolled back without losing communication history.');
        }

        Schema::dropIfExists('guardian_messages');
    }
};
