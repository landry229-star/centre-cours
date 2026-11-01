<?php

namespace App\Policies;

use App\Models\GuardianConversation;
use App\Models\User;

class GuardianConversationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || ($user->isGuardian() && $user->guardian !== null);
    }

    public function view(User $user, GuardianConversation $guardianConversation): bool
    {
        return $user->isAdmin()
            || ($user->isGuardian()
                && (int) $guardianConversation->guardian?->user_id === (int) $user->id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || ($user->isGuardian() && $user->guardian !== null);
    }
}
