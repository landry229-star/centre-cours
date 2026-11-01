<?php

namespace App\Models;

use App\Enums\EducationLevel;
use App\Enums\Gender;
use App\Enums\TeacherStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Teacher extends Model
{
    use HasFactory, SoftDeletes;

    // matricule, user_id et photo_path : posés côté serveur uniquement (TeacherService).
    protected $fillable = [
        'first_name',
        'last_name',
        'gender',
        'phone',
        'email',
        'address',
        'specialty',
        'education_level',
        'hired_at',
        'employment_ended_at',
        'emergency_contact_name',
        'emergency_contact_phone',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'education_level' => EducationLevel::class,
            'status' => TeacherStatus::class,
            'hired_at' => 'date',
            'employment_ended_at' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(TeacherDocument::class);
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => trim("{$this->last_name} {$this->first_name}"));
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('last_name', 'ilike', $like)
                ->orWhere('first_name', 'ilike', $like)
                ->orWhere('matricule', 'ilike', $like)
                ->orWhere('specialty', 'ilike', $like)
                ->orWhere('phone', 'ilike', $like)
                ->orWhereRaw("(first_name || ' ' || last_name) ilike ?", [$like])
                ->orWhereRaw("(last_name || ' ' || first_name) ilike ?", [$like]);
        });
    }
}
