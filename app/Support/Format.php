<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;

/** Formats d'affichage adaptés au Bénin : FCFA sans décimales, mois en français. */
class Format
{
    public static function date(DateTimeInterface $date): string
    {
        return $date->format('d/m/Y');
    }

    public static function dateTime(DateTimeInterface $date): string
    {
        return CarbonImmutable::instance($date)
            ->setTimezone((string) config('app.timezone'))
            ->format('d/m/Y à H:i');
    }

    /** 15000 → "15 000 FCFA" (espaces insécables : le montant ne se coupe jamais en fin de ligne). */
    public static function fcfa(int|float|null $amount): string
    {
        return number_format((int) $amount, 0, ',', "\u{00A0}")."\u{00A0}FCFA";
    }

    /** 2026-10-01 → "octobre 2026" */
    public static function month(CarbonInterface $date): string
    {
        return $date->copy()->locale((string) config('app.locale'))->translatedFormat('F Y');
    }
}
