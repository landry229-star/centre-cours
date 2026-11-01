<?php

namespace App\Services\Students;

use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Model;

/**
 * Génère les matricules séquentiels par année civile :
 *   élèves      ELV-2026-0001
 *   enseignants ENS-2026-0001
 *
 * - withTrashed() : un matricule archivé n'est JAMAIS réattribué.
 * - Tri alphabétique fiable grâce au zéro-padding (limite : 9 999 par an).
 * - La contrainte UNIQUE en base reste l'arbitre final ; les services réessaient en cas de collision.
 */
class MatriculeGenerator
{
    public function next(): string
    {
        return $this->sequence(Student::class, 'ELV');
    }

    public function nextTeacher(): string
    {
        return $this->sequence(Teacher::class, 'ENS');
    }

    /** @param class-string<Model> $model */
    private function sequence(string $model, string $code): string
    {
        $prefix = $code.'-'.now()->year.'-';

        $last = $model::withTrashed()
            ->where('matricule', 'like', $prefix.'%')
            ->orderByDesc('matricule')
            ->value('matricule');

        $number = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }
}
