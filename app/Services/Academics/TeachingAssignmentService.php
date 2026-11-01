<?php

namespace App\Services\Academics;

use App\Models\AttendanceSession;
use App\Models\Classroom;
use App\Models\TeachingAssignment;
use DomainException;
use Illuminate\Support\Facades\DB;

class TeachingAssignmentService
{
    /** @param array<string, mixed> $data subject_id, teacher_id, coefficient, weekly_hours */
    public function add(Classroom $classroom, array $data): TeachingAssignment
    {
        return $classroom->assignments()->create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(TeachingAssignment $assignment, array $data): TeachingAssignment
    {
        return DB::transaction(function () use ($assignment, $data): TeachingAssignment {
            $assignment = TeachingAssignment::query()
                ->whereKey($assignment->id)
                ->lockForUpdate()
                ->firstOrFail();
            $hasEvaluations = $assignment->evaluations()->exists();

            if ($hasEvaluations
                && ((int) $data['subject_id'] !== $assignment->subject_id
                    || (int) $data['coefficient'] !== $assignment->coefficient)) {
                throw new DomainException("La matière et son coefficient ne peuvent pas être changés après la création d'évaluations.");
            }

            if ($assignment->schedules()->exists()
                && ((int) $data['subject_id'] !== $assignment->subject_id
                    || (int) $data['teacher_id'] !== $assignment->teacher_id)) {
                throw new DomainException("La matière ou l'enseignant ne peuvent pas changer tant que cette affectation figure dans un emploi du temps.");
            }

            $assignment->update($data);

            return $assignment;
        });
    }

    public function remove(TeachingAssignment $assignment): void
    {
        DB::transaction(function () use ($assignment): void {
            $assignment = TeachingAssignment::query()
                ->whereKey($assignment->id)
                ->lockForUpdate()
                ->firstOrFail();

            // L'historique des présences et des évaluations empêche de retirer une affectation déjà utilisée.
            $hasSessions = AttendanceSession::query()
                ->where('classroom_id', $assignment->classroom_id)
                ->where('subject_id', $assignment->subject_id)
                ->exists();

            if ($hasSessions) {
                throw new DomainException("Des feuilles d'appel existent pour cette matière dans cette classe : elle ne peut plus être retirée.");
            }

            if ($assignment->evaluations()->exists()) {
                throw new DomainException('Des évaluations existent pour cette affectation : elle ne peut plus être retirée.');
            }

            if ($assignment->schedules()->exists()) {
                throw new DomainException('Cette affectation figure dans un emploi du temps et ne peut pas être retirée.');
            }

            $assignment->delete();
        });
    }
}
