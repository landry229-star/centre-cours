<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceSessionType;
use App\Enums\AttendanceStatus;
use App\Enums\ClassroomStatus;
use App\Events\StudentMarkedAbsent;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceSession;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\TeachingAssignment;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Règles métier des présences. Le formulaire web, le scan QR et (Phase 15) l'API REST passent TOUS par ici.
 */
class AttendanceService
{
    /**
     * Ouvre un appel quotidien ou une séance (classe + matière + jour + heure), ou retrouve son existant.
     */
    public function openSession(
        TeachingAssignment $assignment,
        AttendanceSessionType $type,
        CarbonInterface $date,
        string $startsAt,
        ?string $endsAt,
        User $creator,
    ): AttendanceSession {
        $classroom = $assignment->classroom;

        if ($classroom->status !== ClassroomStatus::Active) {
            throw new DomainException("Cette classe n'est pas active : la prise de présence est impossible.");
        }

        $day = $date->copy()->startOfDay();
        $year = $classroom->schoolYear;

        if ($day->gt(now()->startOfDay())) {
            throw new DomainException('La date de la séance ne peut pas être dans le futur.');
        }

        if ($day->lt($year->start_date->copy()->startOfDay()) || $day->gt($year->end_date->copy()->endOfDay())) {
            throw new DomainException("Cette date est en dehors de l'année scolaire {$year->label}.");
        }

        $daily = $type === AttendanceSessionType::Daily;
        $slot = [
            'classroom_id' => $classroom->id,
            'session_type' => $type->value,
            'session_date' => $day->toDateString(),
            'starts_at' => $daily ? '00:00:00' : $startsAt,
        ];

        if (! $daily) {
            $slot['subject_id'] = $assignment->subject_id;
        }

        $existing = AttendanceSession::where($slot)->first();

        if ($existing !== null) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($slot, $assignment, $endsAt, $creator, $daily) {
                $session = new AttendanceSession;
                $session->forceFill($slot + [
                    'teacher_id' => $assignment->teacher_id,
                    'ends_at' => $daily ? null : $endsAt,
                    'created_by' => $creator->id,
                ])->save();

                return $session;
            });
        } catch (UniqueConstraintViolationException) {
            // Deux clics simultanés : l'autre requête a gagné, on retrouve sa séance.
            return AttendanceSession::where($slot)->firstOrFail();
        }
    }

    /**
     * Élèves attendus à cette séance : inscrits dans la classe CE jour-là.
     * (Un élève arrivé le mois suivant n'apparaît pas sur les séances précédentes ; un élève parti en cours
     * d'année reste visible sur les séances d'avant son départ.)
     *
     * @return Collection<int, Enrollment>
     */
    public function roster(AttendanceSession $session): Collection
    {
        $date = $session->session_date->toDateString();

        return Enrollment::query()
            ->where('classroom_id', $session->classroom_id)
            ->where('enrolled_at', '<=', $date)
            ->where(fn ($q) => $q->whereNull('ended_at')->orWhere('ended_at', '>=', $date))
            ->with('student')
            ->get()
            ->sortBy(fn (Enrollment $e) => $e->student->full_name)
            ->values();
    }

    /**
     * Enregistre (ou corrige) la feuille d'appel. Idempotent : renvoyer la même feuille ne crée aucun doublon.
     *
     * @param  array<int|string, array{status: string, arrived_at?: ?string, note?: ?string}>  $entries  clé = enrollment_id
     * @return array<string, int> effectifs par statut + total
     */
    public function record(AttendanceSession $session, array $entries, User $user): array
    {
        $newlyAbsent = [];

        $summary = DB::transaction(function () use ($session, $entries, $user, &$newlyAbsent) {
            // Verrou : deux enregistrements simultanés de la même feuille se succèdent au lieu de se mélanger.
            AttendanceSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

            $rosterIds = $this->roster($session)->pluck('id')->all();
            $existing = Attendance::where('attendance_session_id', $session->id)->get()->keyBy('enrollment_id');
            $rows = [];

            foreach ($entries as $enrollmentId => $entry) {
                $enrollmentId = (int) $enrollmentId;

                if (! in_array($enrollmentId, $rosterIds, true)) {
                    throw new DomainException("Un élève de la feuille n'est pas inscrit dans cette classe à cette date.");
                }

                $status = AttendanceStatus::tryFrom((string) ($entry['status'] ?? ''));

                if ($status === null) {
                    throw new DomainException('Statut de présence invalide.');
                }

                $rows[] = $this->row($session, $enrollmentId, $status, $entry['arrived_at'] ?? null, $entry['note'] ?? null, $user);

                if ($status === AttendanceStatus::Absent && $existing->get($enrollmentId)?->status !== AttendanceStatus::Absent) {
                    $newlyAbsent[] = $enrollmentId;
                }
            }

            $this->upsert($rows);

            return $this->summary($session);
        });

        // Après validation de la transaction : les écouteurs (notification au parent, Phase 13) voient les données.
        if ($newlyAbsent !== []) {
            Attendance::where('attendance_session_id', $session->id)
                ->whereIn('enrollment_id', $newlyAbsent)
                ->get()
                ->each(fn (Attendance $a) => StudentMarkedAbsent::dispatch($a));
        }

        return $summary;
    }

    /**
     * Pointage par QR Code : le jeton lu sur la carte de l'élève le marque présent (ou en retard).
     *
     * @return array{enrollment_id: int, student: Student, status: AttendanceStatus, already: bool}
     */
    public function markByQr(AttendanceSession $session, string $token, User $user, ?CarbonInterface $now = null): array
    {
        $now ??= now();
        $token = trim($token);

        $student = $token !== '' && strlen($token) <= 100 ? Student::where('qr_token', $token)->first() : null;

        if ($student === null) {
            throw new DomainException('QR Code inconnu.');
        }

        $enrollment = $this->roster($session)->firstWhere('student_id', $student->id);

        if ($enrollment === null) {
            throw new DomainException("{$student->full_name} n'est pas inscrit(e) dans cette classe.");
        }

        $existing = Attendance::where('attendance_session_id', $session->id)
            ->where('enrollment_id', $enrollment->id)
            ->first();

        if ($existing !== null && $existing->status->isAttending()) {
            return ['enrollment_id' => $enrollment->id, 'student' => $student, 'status' => $existing->status, 'already' => true];
        }

        $status = $this->isLate($session, $now) ? AttendanceStatus::Late : AttendanceStatus::Present;

        // Un scan prouve la présence : il remplace un « absent » saisi à la main avant l'arrivée de l'élève.
        $this->upsert([$this->row($session, $enrollment->id, $status, $now->format('H:i'), $existing?->note, $user)]);

        return ['enrollment_id' => $enrollment->id, 'student' => $student, 'status' => $status, 'already' => false];
    }

    /** @return array<string, int> */
    public function summary(AttendanceSession $session): array
    {
        $counts = Attendance::where('attendance_session_id', $session->id)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->toBase()
            ->pluck('total', 'status');

        $summary = [];

        foreach (AttendanceStatus::cases() as $status) {
            $summary[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        $summary['total'] = array_sum($summary);

        return $summary;
    }

    /** Retard seulement le jour même de la séance et au-delà du délai de tolérance. */
    private function isLate(AttendanceSession $session, CarbonInterface $now): bool
    {
        if ($session->isDaily() || ! $session->session_date->isSameDay($now)) {
            return false;
        }

        $start = Carbon::parse($session->session_date->toDateString().' '.$session->startTime());

        return $now->gt($start->addMinutes((int) config('attendance.late_after_minutes', 10)));
    }

    /** @return array<string, mixed> */
    private function row(AttendanceSession $session, int $enrollmentId, AttendanceStatus $status, ?string $arrivedAt, ?string $note, User $user): array
    {
        $arrivedAt = $status->isAttending() && $arrivedAt !== null && trim($arrivedAt) !== '' ? trim($arrivedAt) : null;
        $note = $note !== null && trim($note) !== '' ? trim($note) : null;

        return [
            'attendance_session_id' => $session->id,
            'classroom_id' => $session->classroom_id,
            'enrollment_id' => $enrollmentId,
            'status' => $status->value,
            'arrived_at' => $arrivedAt,
            'note' => $note,
            'marked_by' => $user->id,
        ];
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function upsert(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        DB::transaction(function () use ($rows) {
            $enrollmentIds = array_column($rows, 'enrollment_id');
            $sessionId = $rows[0]['attendance_session_id'];
            $existing = Attendance::query()
                ->where('attendance_session_id', $sessionId)
                ->whereIn('enrollment_id', $enrollmentIds)
                ->get()
                ->keyBy('enrollment_id');

            foreach ($rows as $row) {
                $previous = $existing->get($row['enrollment_id']);

                if ($previous === null || ! $this->hasAttendanceChanges($previous, $row)) {
                    continue;
                }

                AttendanceCorrection::query()->create([
                    'attendance_id' => $previous->id,
                    'corrected_by' => $row['marked_by'],
                    'before_status' => $previous->status->value,
                    'after_status' => $row['status'],
                    'before_arrived_at' => $previous->arrived_at,
                    'after_arrived_at' => $row['arrived_at'],
                    'before_note' => $previous->note,
                    'after_note' => $row['note'],
                ]);
            }

            // INSERT ... ON CONFLICT (séance, inscription) DO UPDATE : atomique, jamais de doublon.
            Attendance::upsert($rows, ['attendance_session_id', 'enrollment_id'], ['status', 'arrived_at', 'note', 'marked_by']);
        });
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function hasAttendanceChanges(Attendance $previous, array $row): bool
    {
        return $previous->status->value !== $row['status']
            || $this->normalizeTime($previous->arrived_at) !== $this->normalizeTime($row['arrived_at'])
            || $previous->note !== $row['note'];
    }

    private function normalizeTime(?string $time): ?string
    {
        return $time === null ? null : substr($time, 0, 5);
    }
}
