<?php

namespace App\Services\Academics;

use App\Enums\ClassroomStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\StudentStatus;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Student;
use App\Support\Format;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Règles métier des inscriptions. Les contrôleurs et (plus tard) l'API passent TOUS par ici.
 * Les contraintes PostgreSQL restent le dernier rempart si deux requêtes arrivent en même temps.
 */
class EnrollmentService
{
    /** Inscrit un élève dans une classe (première inscription ou réinscription d'une nouvelle année). */
    public function enroll(Student $student, Classroom $classroom, ?CarbonInterface $date = null): Enrollment
    {
        $date ??= now();

        try {
            return DB::transaction(function () use ($student, $classroom, $date) {
                $classroom = $this->lockClassroom($classroom->id);
                $student = Student::query()->whereKey($student->id)->lockForUpdate()->firstOrFail();

                $this->assertClassroomOpen($classroom);

                if ($student->status !== StudentStatus::Active) {
                    throw new DomainException("Cet élève n'est pas actif : inscription impossible.");
                }

                $alreadyEnrolled = Enrollment::active()
                    ->where('student_id', $student->id)
                    ->where('school_year_id', $classroom->school_year_id)
                    ->exists();

                if ($alreadyEnrolled) {
                    throw new DomainException('Cet élève a déjà une inscription en cours pour cette année scolaire. Utilisez « Changer de classe ».');
                }

                $this->assertCapacity($classroom);

                return $this->create($student->id, $classroom, $date);
            });
        } catch (UniqueConstraintViolationException) {
            throw new DomainException('Cet élève vient d\'être inscrit pour cette année scolaire.');
        }
    }

    /** Change un élève de classe (même année scolaire) en conservant l'historique. */
    public function transfer(Enrollment $enrollment, Classroom $target, ?CarbonInterface $date = null): Enrollment
    {
        $date ??= now();

        try {
            return DB::transaction(function () use ($enrollment, $target, $date) {
                $current = $this->lockActiveEnrollment($enrollment->id);
                $target = $this->lockClassroom($target->id);

                if ($target->id === $current->classroom_id) {
                    throw new DomainException("L'élève est déjà dans cette classe.");
                }

                if ($target->school_year_id !== $current->school_year_id) {
                    throw new DomainException("Le changement de classe doit rester dans la même année scolaire. Pour l'année suivante, créez une nouvelle inscription.");
                }

                $this->assertClassroomOpen($target);
                $this->assertCapacity($target);
                $this->assertEndDate($current, $date);

                // L'ancienne inscription est fermée AVANT d'ouvrir la nouvelle (index unique partiel).
                $current->forceFill([
                    'status' => EnrollmentStatus::Transferred,
                    'ended_at' => $date->toDateString(),
                ])->save();

                return $this->create($current->student_id, $target, $date);
            });
        } catch (UniqueConstraintViolationException) {
            throw new DomainException('Une autre modification de cette inscription vient d\'avoir lieu. Rechargez la page.');
        }
    }

    /** Retire un élève en cours d'année (départ, abandon). L'historique est conservé. */
    public function withdraw(Enrollment $enrollment, ?CarbonInterface $date = null): Enrollment
    {
        $date ??= now();

        return DB::transaction(function () use ($enrollment, $date) {
            $current = $this->lockActiveEnrollment($enrollment->id);
            $this->assertEndDate($current, $date);

            $current->forceFill([
                'status' => EnrollmentStatus::Withdrawn,
                'ended_at' => $date->toDateString(),
            ])->save();

            return $current;
        });
    }

    private function create(int $studentId, Classroom $classroom, CarbonInterface $date): Enrollment
    {
        $enrollment = new Enrollment(['enrolled_at' => $date->toDateString()]);
        $enrollment->student_id = $studentId;
        $enrollment->classroom_id = $classroom->id;
        $enrollment->school_year_id = $classroom->school_year_id;
        $enrollment->status = EnrollmentStatus::Active;
        $enrollment->save();

        return $enrollment;
    }

    /** Verrou de ligne : deux inscriptions simultanées dans la même classe se mettent en file. */
    private function lockClassroom(int $id): Classroom
    {
        return Classroom::query()->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    private function lockActiveEnrollment(int $id): Enrollment
    {
        $enrollment = Enrollment::query()->whereKey($id)->lockForUpdate()->firstOrFail();

        if (! $enrollment->isActive()) {
            throw new DomainException("Cette inscription n'est plus en cours.");
        }

        return $enrollment;
    }

    private function assertClassroomOpen(Classroom $classroom): void
    {
        if ($classroom->status !== ClassroomStatus::Active) {
            throw new DomainException('Cette classe est clôturée : inscription impossible.');
        }
    }

    private function assertCapacity(Classroom $classroom): void
    {
        if ($classroom->activeEnrollmentsCount() >= $classroom->capacity) {
            throw new DomainException("La classe {$classroom->name} est complète ({$classroom->capacity} places).");
        }
    }

    private function assertEndDate(Enrollment $enrollment, CarbonInterface $date): void
    {
        if ($date->toDateString() < $enrollment->enrolled_at->toDateString()) {
            throw new DomainException('La date ne peut pas précéder la date d’inscription ('.Format::date($enrollment->enrolled_at).').');
        }
    }
}
