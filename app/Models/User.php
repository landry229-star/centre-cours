<?php

namespace App\Models;

use App\Enums\NotificationCategory;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * SÉCURITÉ : "role" et "is_active" sont volontairement absents de $fillable.
     * Ainsi, aucun formulaire ne peut s'attribuer un rôle par mass assignment.
     * Le rôle est toujours affecté explicitement par du code serveur.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'notification_preferences' => 'array',
        ];
    }

    public function receivesNotificationCategory(string $category): bool
    {
        $notificationCategory = NotificationCategory::tryFrom($category);

        if ($notificationCategory === null || $notificationCategory === NotificationCategory::Message) {
            return true;
        }

        return $this->notification_preferences[$notificationCategory->value] ?? true;
    }

    /** Profil enseignant lié à ce compte (null pour les autres rôles). */
    public function teacher(): HasOne
    {
        return $this->hasOne(Teacher::class);
    }

    /** Profil parent lié à ce compte (null pour les autres rôles). */
    public function guardian(): HasOne
    {
        return $this->hasOne(Guardian::class);
    }

    /** Profil élève lié à ce compte (null pour les autres rôles). */
    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function hasRole(Role|string ...$roles): bool
    {
        foreach ($roles as $role) {
            $role = $role instanceof Role ? $role : Role::tryFrom($role);

            if ($role !== null && $this->role === $role) {
                return true;
            }
        }

        return false;
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isTeacher(): bool
    {
        return $this->role === Role::Teacher;
    }

    public function isGuardian(): bool
    {
        return $this->role === Role::Guardian;
    }

    public function isStudent(): bool
    {
        return $this->role === Role::Student;
    }
}
