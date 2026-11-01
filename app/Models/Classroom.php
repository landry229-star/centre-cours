<?php

namespace App\Models;

use App\Enums\ClassroomStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Classroom extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_year_id',
        'level_id',
        'name',
        'capacity',
        'room_id',
        'head_teacher_id',
        'status',
    ];

    protected function casts(): array
    {
        return ['status' => ClassroomStatus::class];
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function headTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'head_teacher_id')->withTrashed();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /** Nombre d'élèves actuellement inscrits (inscriptions en cours). */
    public function activeEnrollmentsCount(): int
    {
        return $this->enrollments()->active()->count();
    }

    /** Vrai si l'enseignant est professeur principal OU enseigne une matière dans cette classe. */
    public function isTaughtBy(Teacher $teacher): bool
    {
        return $this->head_teacher_id === $teacher->id
            || $this->assignments()->where('teacher_id', $teacher->id)->exists();
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        return $query->where(function (Builder $query) use ($like, $term): void {
            $query->where('name', 'ilike', $like)
                ->orWhereHas('level', fn (Builder $levelQuery) => $levelQuery
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($term)]));
        });
    }
}
