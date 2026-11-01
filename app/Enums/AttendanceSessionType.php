<?php

namespace App\Enums;

enum AttendanceSessionType: string
{
    case Daily = 'daily';
    case Lesson = 'lesson';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Appel quotidien',
            self::Lesson => 'Séance / matière',
        };
    }
}
