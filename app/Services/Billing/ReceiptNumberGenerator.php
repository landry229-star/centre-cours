<?php

namespace App\Services\Billing;

use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Numéros de reçu REC-2026-000001 : uniques, croissants et SANS trou.
 *
 * Pourquoi un compteur verrouillé plutôt que MAX(id)+1 ? Deux caissiers qui valident
 * en même temps liraient le même MAX et produiraient le même numéro. Ici, la ligne du
 * compteur est verrouillée (FOR UPDATE) : le second attend que le premier ait fini.
 * À appeler OBLIGATOIREMENT dans la transaction qui crée le paiement : si elle échoue,
 * le numéro n'est pas consommé (pas de trou).
 */
class ReceiptNumberGenerator
{
    public function next(?int $year = null): string
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('ReceiptNumberGenerator doit être appelé dans une transaction.');
        }

        $year ??= (int) now()->format('Y');

        // ON CONFLICT DO NOTHING : n'interrompt pas la transaction PostgreSQL s'il existe déjà.
        DB::table('receipt_counters')->insertOrIgnore(['year' => $year, 'last_number' => 0]);

        $row = DB::table('receipt_counters')->where('year', $year)->lockForUpdate()->first();
        $next = $row->last_number + 1;

        DB::table('receipt_counters')->where('year', $year)->update(['last_number' => $next]);

        return sprintf('REC-%d-%06d', $year, $next);
    }
}
