<?php

namespace App\Services\Academics;

use App\Enums\ClassroomStatus;
use App\Enums\TeacherStatus;
use App\Events\ScheduleChanged;
use App\Models\Classroom;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\SchoolYear;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ScheduleService
{
    /** @param array{assignment_id: int|string, room_id: int|string, day_of_week: string, starts_at: string, ends_at: string} $data */
    public function create(array $data, User $actor): Schedule
    {
        $schedule = $this->save(null, $data, $actor);
        ScheduleChanged::dispatch($schedule, 'created');

        return $schedule;
    }

    /** @param array{assignment_id: int|string, room_id: int|string, day_of_week: string, starts_at: string, ends_at: string} $data */
    public function update(Schedule $schedule, array $data, User $actor): Schedule
    {
        $schedule = $this->save($schedule, $data, $actor);

        if ($schedule->wasChanged()) {
            ScheduleChanged::dispatch($schedule, 'updated');
        }

        return $schedule;
    }

    public function delete(Schedule $schedule): void
    {
        $deletedSchedule = DB::transaction(function () use ($schedule): Schedule {
            $deletedSchedule = Schedule::query()
                ->whereKey($schedule->id)
                ->lockForUpdate()
                ->firstOrFail();
            $deletedSchedule->delete();

            return $deletedSchedule;
        });

        ScheduleChanged::dispatch($deletedSchedule, 'deleted');
    }

    /**
     * Serialize timetable writes on the class, room, and teacher rows so simultaneous requests
     * cannot both pass the conflict checks for the same resource.
     *
     * @param  array{assignment_id: int|string, room_id: int|string, day_of_week: string, starts_at: string, ends_at: string}  $data
     */
    private function save(?Schedule $schedule, array $data, User $actor): Schedule
    {
        return DB::transaction(function () use ($schedule, $data, $actor): Schedule {
            if ($schedule !== null) {
                $schedule = Schedule::query()->whereKey($schedule->id)->lockForUpdate()->firstOrFail();
            }

            $assignment = TeachingAssignment::query()
                ->whereKey((int) $data['assignment_id'])
                ->lockForUpdate()
                ->firstOrFail();
            $teacher = Teacher::withTrashed()
                ->whereKey($assignment->teacher_id)
                ->lockForUpdate()
                ->firstOrFail();
            $room = Room::query()
                ->whereKey((int) $data['room_id'])
                ->lockForUpdate()
                ->firstOrFail();
            $classroom = Classroom::query()
                ->whereKey($assignment->classroom_id)
                ->lockForUpdate()
                ->firstOrFail();
            $schoolYear = SchoolYear::query()
                ->whereKey($classroom->school_year_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($classroom->status !== ClassroomStatus::Active || ! $schoolYear->is_active) {
                throw new DomainException("L'emploi du temps ne peut être modifié que pour une classe et une année scolaire actives.");
            }

            if ($teacher->trashed() || $teacher->status !== TeacherStatus::Active) {
                throw new DomainException("L'enseignant affecté n'est pas actif.");
            }

            $this->assertNoConflicts(
                $classroom,
                $assignment,
                $room,
                $data['day_of_week'],
                $data['starts_at'],
                $data['ends_at'],
                $schedule?->id,
            );

            $schedule ??= new Schedule;
            $schedule->forceFill([
                'classroom_id' => $classroom->id,
                'teaching_assignment_id' => $assignment->id,
                'room_id' => $room->id,
                'created_by' => $schedule->exists ? $schedule->created_by : $actor->id,
                'day_of_week' => $data['day_of_week'],
                'starts_at' => $this->databaseTime($data['starts_at']),
                'ends_at' => $this->databaseTime($data['ends_at']),
            ])->save();

            return $schedule;
        });
    }

    private function assertNoConflicts(
        Classroom $classroom,
        TeachingAssignment $assignment,
        Room $room,
        string $dayOfWeek,
        string $startsAt,
        string $endsAt,
        ?int $ignoreScheduleId,
    ): void {
        $overlapping = Schedule::query()
            ->where('day_of_week', $dayOfWeek)
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->whereHas('classroom', fn (Builder $query) => $query
                ->withTrashed()
                ->where('school_year_id', $classroom->school_year_id))
            ->when($ignoreScheduleId, fn (Builder $query) => $query->where('schedules.id', '<>', $ignoreScheduleId));

        if ((clone $overlapping)->where('classroom_id', $classroom->id)->exists()) {
            throw new DomainException('Cette classe a déjà un cours à cet horaire.');
        }

        if ((clone $overlapping)->whereHas('assignment', fn (Builder $query) => $query
            ->where('teacher_id', $assignment->teacher_id))->exists()) {
            throw new DomainException('Cet enseignant a déjà un cours à cet horaire.');
        }

        if ((clone $overlapping)->where('room_id', $room->id)->exists()) {
            throw new DomainException('Cette salle est déjà occupée à cet horaire.');
        }
    }

    private function databaseTime(string $time): string
    {
        return strlen($time) === 5 ? $time.':00' : $time;
    }
}
