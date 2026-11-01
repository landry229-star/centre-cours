<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum EvaluationType: string
{
    use HasOptions;

    case Homework = 'homework';
    case Quiz = 'quiz';
    case Composition = 'composition';
    case Exam = 'exam';
    case Project = 'project';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Homework => 'Devoir',
            self::Quiz => 'Interrogation',
            self::Composition => 'Composition',
            self::Exam => 'Examen',
            self::Project => 'Projet',
            self::Other => 'Autre',
        };
    }
}
