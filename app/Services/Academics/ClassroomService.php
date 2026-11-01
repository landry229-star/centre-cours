<?php

namespace App\Services\Academics;

use App\Models\Classroom;
use DomainException;
use Illuminate\Support\Facades\DB;

class ClassroomService
{
    /** @param array<string, mixed> $data */
    public function create(array $data): Classroom
    {
        return Classroom::create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(Classroom $classroom, array $data): Classroom
    {
        return DB::transaction(function () use ($classroom, $data) {
            $classroom = Classroom::query()->whereKey($classroom->id)->lockForUpdate()->firstOrFail();

            $levelChanged = isset($data['level_id'])
                && (int) $data['level_id'] !== $classroom->level_id;
            $schoolYearChanged = isset($data['school_year_id'])
                && (int) $data['school_year_id'] !== $classroom->school_year_id;

            if (($levelChanged || $schoolYearChanged) && $classroom->enrollments()->exists()) {
                throw new DomainException('Impossible de modifier le niveau ou l’année de cette classe : des inscriptions existent déjà. L’historique scolaire et financier est verrouillé.');
            }

            if ($schoolYearChanged && $classroom->assignments()->exists()) {
                throw new DomainException('Impossible de changer l’année de cette classe : des affectations et évaluations sont déjà liées à cette classe.');
            }

            $classroom->update($data);

            return $classroom;
        });
    }

    /**
     * Suppression réservée aux classes créées par erreur.
     * Une classe utilisée doit être « clôturée » (statut), pas supprimée.
     * Les inscriptions (Phase 8) renforceront cette règle.
     */
    public function delete(Classroom $classroom): void
    {
        DB::transaction(function () use ($classroom) {
            $classroom = Classroom::query()
                ->whereKey($classroom->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($classroom->assignments()->exists()) {
                throw new DomainException('Impossible de supprimer cette classe : des enseignants y sont affectés. Clôturez-la plutôt.');
            }

            if ($classroom->enrollments()->active()->exists()) {
                throw new DomainException("Impossible d'archiver cette classe : des inscriptions sont encore en cours. Clôturez-les d'abord.");
            }

            $classroom->delete();
        });
    }
}
