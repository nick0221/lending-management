<?php

namespace App\Policies;

use App\Models\User;

class NotificationLogPolicy
{
    /**
     * Determine whether the user can view the notification delivery log.
     */
    public function viewAny(User $user): bool
    {
        return $user->canAccessAdminPanel();
    }
}
