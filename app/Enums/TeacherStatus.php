<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum TeacherStatus: string
{
    use HasOptions;

    case Active = 'active';
    case Inactive = 'inactive';
    case OnLeave = 'on_leave';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Actif',
            self::Inactive => 'Inactif',
            self::OnLeave => 'En congé',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Inactive => 'gray',
            self::OnLeave => 'yellow',
        };
    }
}
