<?php

namespace App\Policies;

use App\Models\User;

class DisbursementPolicy
{
    /**
     * Determine whether the user can view the disbursements register.
     */
    public function viewAny(User $user): bool
    {
        return $user->canAccessAdminPanel();
    }
}
