<?php

namespace App\Policies;

use App\Models\ActivityLog;
use App\Models\User;

class ActivityLogPolicy
{
    /**
     * Determine whether the user can browse the audit trail.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can view the given activity entry.
     */
    public function view(User $user, ActivityLog $activityLog): bool
    {
        return $user->isSuperAdmin();
    }
}
