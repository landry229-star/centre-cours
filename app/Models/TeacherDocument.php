<?php

namespace App\Models;

use App\Enums\TeacherDocumentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'uploaded_by',
        'type',
        'document_date',
        'expires_at',
        'original_filename',
        'path',
    ];

    protected function casts(): array
    {
        return [
            'type' => TeacherDocumentType::class,
            'document_date' => 'date',
            'expires_at' => 'date',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class)->withTrashed();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
