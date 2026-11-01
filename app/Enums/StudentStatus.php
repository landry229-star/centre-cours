<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum StudentStatus: string
{
    use HasOptions;

    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Actif',
            self::Inactive => 'Inactif',
            self::Suspended => 'Suspendu',
        };
    }

    /** Couleur du badge (voir components/badge.blade.php). */
    public function color(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Inactive => 'gray',
            self::Suspended => 'red',
        };
    }
}
