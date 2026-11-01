<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum EnrollmentStatus: string
{
    use HasOptions;

    case Active = 'active';           // inscription en cours
    case Transferred = 'transferred'; // l'élève a changé de classe (une nouvelle inscription existe)
    case Withdrawn = 'withdrawn';     // départ / abandon en cours d'année
    case Completed = 'completed';     // année scolaire terminée

    public function label(): string
    {
        return match ($this) {
            self::Active => 'En cours',
            self::Transferred => 'Changement de classe',
            self::Withdrawn => 'Retrait',
            self::Completed => 'Terminée',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Transferred => 'blue',
            self::Withdrawn => 'red',
            self::Completed => 'gray',
        };
    }
}
