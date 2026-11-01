<?php

namespace App\Models;

use Database\Factories\FeeDueDateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeeDueDate extends Model
{
    /** @use HasFactory<FeeDueDateFactory> */
    use HasFactory;

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'month' => 'date',
            'due_date' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function changes(): HasMany
    {
        return $this->hasMany(FeeDueDateChange::class)->orderByDesc('created_at')->orderByDesc('id');
    }
}
