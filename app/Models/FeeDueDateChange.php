<?php

namespace App\Models;

use Database\Factories\FeeDueDateChangeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeDueDateChange extends Model
{
    /** @use HasFactory<FeeDueDateChangeFactory> */
    use HasFactory;

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'old_due_date' => 'date',
            'new_due_date' => 'date',
        ];
    }

    public function feeDueDate(): BelongsTo
    {
        return $this->belongsTo(FeeDueDate::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
