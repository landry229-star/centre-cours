<?php

namespace App\Models;

use App\Enums\AttendanceSessionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceSession extends Model
{
    use HasFactory;

    // Écrit uniquement par AttendanceService.
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'session_type' => AttendanceSessionType::class,
        ];
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class)->withTrashed();
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /** Heure de début au format 08:00 (la base renvoie 08:00:00). */
    public function startTime(): string
    {
        return substr((string) $this->starts_at, 0, 5);
    }

    public function endTime(): ?string
    {
        return $this->ends_at ? substr((string) $this->ends_at, 0, 5) : null;
    }

    public function isDaily(): bool
    {
        return $this->session_type === AttendanceSessionType::Daily;
    }

    /** Les enseignants voient leurs cours et les appels quotidiens de leurs classes ; l'admin voit tout. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        if ($user->isTeacher() && $user->teacher !== null) {
            return $query->where(function (Builder $query) use ($user) {
                $query->where('attendance_sessions.teacher_id', $user->teacher->id)
                    ->orWhereExists(fn ($assignments) => $assignments
                        ->selectRaw('1')
                        ->from('teaching_assignments')
                        ->whereColumn('teaching_assignments.classroom_id', 'attendance_sessions.classroom_id')
                        ->where('teaching_assignments.teacher_id', $user->teacher->id)
                        ->where(function ($query) {
                            $query->where('attendance_sessions.session_type', 'daily')
                                ->orWhereColumn('teaching_assignments.subject_id', 'attendance_sessions.subject_id');
                        }));
            });
        }

        return $query->whereRaw('1 = 0');
    }
}
