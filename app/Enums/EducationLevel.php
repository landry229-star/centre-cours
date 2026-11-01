<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum EducationLevel: string
{
    use HasOptions;

    case Bac = 'bac';
    case Licence = 'licence';
    case Master = 'master';
    case Doctorat = 'doctorat';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Bac => 'Baccalauréat',
            self::Licence => 'Licence',
            self::Master => 'Master',
            self::Doctorat => 'Doctorat',
            self::Other => 'Autre',
        };
    }
}
