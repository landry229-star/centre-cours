<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use App\Enums\StudentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * SÉCURITÉ : matricule, qr_token, photo_path et user_id ne sont PAS fillable.
     * Ils sont générés/validés côté serveur (StudentService), jamais par un formulaire.
     */
    protected $fillable = [
        'guardian_id',
        'first_name',
        'last_name',
        'gender',
        'birth_date',
        'birth_place',
        'phone',
        'address',
        'guardian_relationship',
        'emergency_contact_name',
        'emergency_contact_phone',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'birth_date' => 'date',
            'guardian_relationship' => GuardianRelationship::class,
            'status' => StudentStatus::class,
        ];
    }

    // withTrashed : la fiche d'un élève reste lisible même si son tuteur est archivé.
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class)->withTrashed();
    }

    /** Historique complet, de la plus récente à la plus ancienne. */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class)->orderByDesc('enrolled_at')->orderByDesc('id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** "DOSSOU Koffi" */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => trim("{$this->last_name} {$this->first_name}"));
    }

    protected function age(): Attribute
    {
        return Attribute::get(fn () => $this->birth_date?->age);
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
                ->orWhere('phone', 'ilike', $like)
                ->orWhereRaw("(first_name || ' ' || last_name) ilike ?", [$like])
                ->orWhereRaw("(last_name || ' ' || first_name) ilike ?", [$like]);
        });
    }
}
