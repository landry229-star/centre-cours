<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ClassroomStatus: string
{
    use HasOptions;

    case Active = 'active';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Ouverte',
            self::Closed => 'Clôturée',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Closed => 'gray',
        };
    }
}
