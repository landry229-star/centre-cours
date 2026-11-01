<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GuardianConversation extends Model
{
    use HasFactory;

    protected $fillable = ['last_message_at'];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(GuardianMessage::class);
    }

    public function latestMessage(): HasMany
    {
        return $this->hasMany(GuardianMessage::class)->latest('id')->limit(1);
    }
}
