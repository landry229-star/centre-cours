<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum GuardianRelationship: string
{
    use HasOptions;

    case Father = 'father';
    case Mother = 'mother';
    case Guardian = 'guardian';
    case Uncle = 'uncle';
    case Aunt = 'aunt';
    case Sibling = 'sibling';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Father => 'Père',
            self::Mother => 'Mère',
            self::Guardian => 'Tuteur / Tutrice',
            self::Uncle => 'Oncle',
            self::Aunt => 'Tante',
            self::Sibling => 'Frère / Sœur',
            self::Other => 'Autre',
        };
    }
}
