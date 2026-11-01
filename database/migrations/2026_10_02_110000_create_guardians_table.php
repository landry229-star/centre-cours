<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            // Compte de connexion optionnel. Un compte = un seul parent (unique).
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('phone', 30);
            // Email de contact. Volontairement NON unique : deux parents peuvent partager un email.
            // L'unicité est garantie sur users.email lorsqu'un accès est créé.
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('profession', 150)->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['last_name', 'first_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardians');
    }
};
