<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Statut d'UNE mensualité (élève + mois). Il n'est jamais stocké : il se CALCULE
 * à partir du tarif et des paiements, donc il ne peut pas devenir incohérent.
 */
enum PaymentStatus: string
{
    use HasOptions;

    case Paid = 'paid';
    case Partial = 'partial';
    case Unpaid = 'unpaid';

    public function label(): string
    {
        return match ($this) {
            self::Paid => 'Payé',
            self::Partial => 'Partiellement payé',
            self::Unpaid => 'Impayé',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Paid => 'green',
            self::Partial => 'yellow',
            self::Unpaid => 'red',
        };
    }

    public static function fromAmounts(int $expected, int $paid): self
    {
        return match (true) {
            $paid >= $expected => self::Paid,
            $paid > 0 => self::Partial,
            default => self::Unpaid,
        };
    }
}
