<?php

namespace App\Policies;

use App\Models\User;

class PaymentPolicy
{
    /**
     * Determine whether the user can view the payments register.
     */
    public function viewAny(User $user): bool
    {
        return $user->canAccessAdminPanel();
    }
}
