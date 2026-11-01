<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum AttendanceStatus: string
{
    use HasOptions;

    case Present = 'present';
    case Absent = 'absent';
    case Late = 'late';
    case Excused = 'excused'; // absence justifiée

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Présent',
            self::Absent => 'Absent',
            self::Late => 'Retard',
            self::Excused => 'Absence justifiée',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Present => 'green',
            self::Absent => 'red',
            self::Late => 'yellow',
            self::Excused => 'blue',
        };
    }

    /** L'élève était physiquement en classe (présent ou en retard). */
    public function isAttending(): bool
    {
        return $this === self::Present || $this === self::Late;
    }
}
