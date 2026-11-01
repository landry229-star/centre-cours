<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Teacher = 'teacher';
    case Guardian = 'guardian';
    case Student = 'student';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrateur',
            self::Teacher => 'Enseignant',
            self::Guardian => 'Parent / Tuteur',
            self::Student => 'Élève',
        };
    }

    /** Nom de la route du tableau de bord propre à ce rôle. */
    public function dashboardRoute(): string
    {
        return match ($this) {
            self::Admin => 'admin.dashboard',
            self::Teacher => 'teacher.dashboard',
            self::Guardian => 'guardian.dashboard',
            self::Student => 'student.dashboard',
        };
    }
}
