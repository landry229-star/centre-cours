<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            // L'inscription donne l'élève, la classe ET l'année : pas de colonnes redondantes.
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->date('month');                       // toujours le 1er du mois concerné
            $table->unsignedInteger('amount');           // FCFA
            $table->string('method', 20);
            $table->string('reference', 100)->nullable(); // n° de transaction Mobile Money, de virement...
            $table->string('payer_name', 150);           // photo du payeur au moment du paiement
            $table->date('paid_at');
            $table->string('receipt_number', 20)->unique();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();

            // Jamais de DELETE sur un paiement : on ANNULE (la trace comptable reste).
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('cancel_reason', 255)->nullable();

            $table->timestamps();

            $table->index(['enrollment_id', 'month']);
            $table->index('paid_at');
            $table->index('month');
        });

        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_check CHECK (amount > 0)');
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_method_check CHECK (method IN ('cash', 'mobile_money', 'bank_transfer', 'other'))");
        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_month_first_day_check CHECK (EXTRACT(DAY FROM month) = 1)');
        // Annulation cohérente : date, auteur et motif vont toujours ensemble.
        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_cancel_check CHECK ((cancelled_at IS NULL) = (cancelled_by IS NULL) AND (cancelled_at IS NULL) = (cancel_reason IS NULL))');

        // Une même référence de transaction (hors espèces) ne peut justifier qu'UN paiement valide.
        DB::statement("CREATE UNIQUE INDEX payments_reference_unique ON payments (method, reference) WHERE reference IS NOT NULL AND cancelled_at IS NULL AND method <> 'cash'");
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
