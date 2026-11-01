<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Guardian extends Model
{
    use HasFactory, SoftDeletes;

    // user_id est absent : le lien avec un compte est géré uniquement par GuardianService.
    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'email',
        'address',
        'profession',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function conversation(): HasOne
    {
        return $this->hasOne(GuardianConversation::class);
    }

    /** "DOSSOU Koffi" */
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

        // On échappe % _ \ pour que l'utilisateur ne puisse pas injecter de jokers LIKE.
        $like = '%'.addcslashes($term, '%_\\').'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('last_name', 'ilike', $like)
                ->orWhere('first_name', 'ilike', $like)
                ->orWhere('phone', 'ilike', $like)
                ->orWhere('email', 'ilike', $like)
                ->orWhereRaw("(first_name || ' ' || last_name) ilike ?", [$like])
                ->orWhereRaw("(last_name || ' ' || first_name) ilike ?", [$like]);
        });
    }
}
