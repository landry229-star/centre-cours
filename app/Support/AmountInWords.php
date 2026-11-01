<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Écrit un montant entier en lettres françaises (orthographe traditionnelle) :
 * 80 000 → "quatre-vingt mille", 200 → "deux cents", 71 → "soixante et onze".
 *
 * Règle d'accord retenue : « cent » et « vingt » (dans quatre-vingts) prennent un s
 * seulement s'ils terminent le nombre, ou s'ils précèdent « million(s) » / « milliard(s) ».
 * Devant « mille » (invariable), pas de s.
 */
class AmountInWords
{
    private const UNITS = [
        'zéro', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf',
        'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize',
        'dix-sept', 'dix-huit', 'dix-neuf',
    ];

    private const TENS = [2 => 'vingt', 3 => 'trente', 4 => 'quarante', 5 => 'cinquante', 6 => 'soixante'];

    private const MAX = 999_999_999_999;

    /** 15000 → "Quinze mille francs CFA" */
    public static function francs(int $amount): string
    {
        $words = self::number($amount);
        $unit = abs($amount) >= 2 ? 'francs' : 'franc';

        return mb_strtoupper(mb_substr($words, 0, 1)).mb_substr($words, 1)." {$unit} CFA";
    }

    public static function number(int $n): string
    {
        if ($n < 0 || $n > self::MAX) {
            throw new InvalidArgumentException('Montant hors limites (0 à 999 999 999 999).');
        }

        if ($n === 0) {
            return self::UNITS[0];
        }

        $parts = [];
        $billions = intdiv($n, 1_000_000_000);
        $millions = intdiv($n % 1_000_000_000, 1_000_000);
        $thousands = intdiv($n % 1_000_000, 1000);
        $rest = $n % 1000;

        if ($billions > 0) {
            $parts[] = self::below1000($billions, true).' milliard'.($billions > 1 ? 's' : '');
        }
        if ($millions > 0) {
            $parts[] = self::below1000($millions, true).' million'.($millions > 1 ? 's' : '');
        }
        if ($thousands > 0) {
            $parts[] = $thousands === 1 ? 'mille' : self::below1000($thousands, false).' mille';
        }
        if ($rest > 0) {
            $parts[] = self::below1000($rest, true);
        }

        return implode(' ', $parts);
    }

    /** 1 à 999. $plural : le dernier « cent » / « vingt » peut-il prendre un s ? */
    private static function below1000(int $n, bool $plural): string
    {
        $hundreds = intdiv($n, 100);
        $rest = $n % 100;
        $parts = [];

        if ($hundreds > 0) {
            $parts[] = $hundreds === 1
                ? 'cent'
                : self::UNITS[$hundreds].' cent'.(($rest === 0 && $plural) ? 's' : '');
        }

        if ($rest > 0) {
            $parts[] = self::below100($rest, $plural);
        }

        return implode(' ', $parts);
    }

    /** 1 à 99. */
    private static function below100(int $n, bool $plural): string
    {
        if ($n < 20) {
            return self::UNITS[$n];
        }

        $tens = intdiv($n, 10);
        $unit = $n % 10;

        if ($tens <= 6) {
            return match (true) {
                $unit === 0 => self::TENS[$tens],
                $unit === 1 => self::TENS[$tens].' et un',
                default => self::TENS[$tens].'-'.self::UNITS[$unit],
            };
        }

        if ($tens === 7) { // 70 à 79 : soixante + (10 à 19)
            return $unit === 1
                ? 'soixante et onze'
                : 'soixante-'.self::UNITS[10 + $unit];
        }

        if ($tens === 8) { // 80 à 89
            return $unit === 0
                ? 'quatre-vingt'.($plural ? 's' : '')
                : 'quatre-vingt-'.self::UNITS[$unit];
        }

        return 'quatre-vingt-'.self::UNITS[10 + $unit]; // 90 à 99
    }
}
