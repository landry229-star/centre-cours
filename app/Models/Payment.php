<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    // Aucun champ en mass assignment : tout passe par PaymentService (montant, n° de reçu, auteur...).
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'month' => 'date',
            'paid_at' => 'date',
            'amount' => 'integer',
            'method' => PaymentMethod::class,
            'cancelled_at' => 'datetime',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /** Paiements valides (non annulés). */
    public function scopeValid(Builder $query): Builder
    {
        return $query->whereNull('payments.cancelled_at');
    }
}
