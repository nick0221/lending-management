<?php

namespace App\Policies;

use App\Models\Borrower;
use App\Models\User;

class BorrowerPolicy
{
    /**
     * Determine whether the user can view the list of borrowers.
     */
    public function viewAny(User $user): bool
    {
        return $user->canAccessAdminPanel();
    }

    /**
     * Determine whether the user can view the given borrower.
     */
    public function view(User $user, Borrower $borrower): bool
    {
        return $user->canAccessAdminPanel();
    }

    /**
     * Determine whether the user can create borrowers.
     */
    public function create(User $user): bool
    {
        return $user->canAccessAdminPanel();
    }

    /**
     * Determine whether the user can update the given borrower.
     */
    public function update(User $user, Borrower $borrower): bool
    {
        return $user->canAccessAdminPanel();
    }

    /**
     * Determine whether the user can update several borrowers at once.
     */
    public function updateAny(User $user): bool
    {
        return $user->canAccessAdminPanel();
    }

    /**
     * Determine whether the user can delete the given borrower.
     */
    public function delete(User $user, Borrower $borrower): bool
    {
        return $user->canAccessAdminPanel();
    }
}
