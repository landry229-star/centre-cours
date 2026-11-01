<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceCorrection extends Model
{
    protected $fillable = [
        'attendance_id',
        'corrected_by',
        'before_status',
        'after_status',
        'before_arrived_at',
        'after_arrived_at',
        'before_note',
        'after_note',
    ];

    protected function casts(): array
    {
        return [
            'before_status' => AttendanceStatus::class,
            'after_status' => AttendanceStatus::class,
            'corrected_at' => 'datetime',
        ];
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function corrector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }
}
