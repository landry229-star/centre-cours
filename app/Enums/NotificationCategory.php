<?php

namespace App\Enums;

enum NotificationCategory: string
{
    case Announcement = 'announcement';
    case Attendance = 'attendance';
    case Grades = 'grades';
    case Schedule = 'schedule';
    case Payment = 'payment';
    case Message = 'message';

    public function label(): string
    {
        return match ($this) {
            self::Announcement => 'Annonce',
            self::Attendance => 'Présence',
            self::Grades => 'Notes',
            self::Schedule => 'Emploi du temps',
            self::Payment => 'Paiements',
            self::Message => 'Message privé',
        };
    }

    public static function displayLabel(?string $category): string
    {
        return self::tryFrom($category ?? '')?->label()
            ?? match ($category) {
                'grade' => 'Notes',
                'communication' => 'Communication',
                default => 'Autre',
            };
    }

    /**
     * @return array<int, self>
     */
    public static function preferenceCategories(): array
    {
        return [
            self::Announcement,
            self::Attendance,
            self::Grades,
            self::Schedule,
            self::Payment,
        ];
    }
}
