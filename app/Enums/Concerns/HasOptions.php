<?php

namespace App\Enums\Concerns;

/**
 * Transforme un enum en liste [valeur => libellé] pour les <select> et les filtres.
 * L'enum doit posséder une méthode label().
 */
trait HasOptions
{
    /** @return array<string, string> */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
