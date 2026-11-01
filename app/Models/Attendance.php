<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attendance extends Model
{
    use HasFactory;

    // Écrit uniquement par AttendanceService (upsert).
    protected $fillable = [];

    protected function casts(): array
    {
        return ['status' => AttendanceStatus::class];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'attendance_session_id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function marker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(AttendanceCorrection::class)->orderByDesc('corrected_at')->orderByDesc('id');
    }

    public function arrivalTime(): ?string
    {
        return $this->arrived_at ? substr((string) $this->arrived_at, 0, 5) : null;
    }
}
