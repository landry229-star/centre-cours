<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum Cycle: string
{
    use HasOptions;

    case Primary = 'primary';
    case College = 'college';
    case Lycee = 'lycee';

    public function label(): string
    {
        return match ($this) {
            self::Primary => 'Primaire',
            self::College => 'Collège',
            self::Lycee => 'Lycée',
        };
    }
}
