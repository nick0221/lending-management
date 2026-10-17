<?php

namespace App\Policies;

use App\Models\LoanProduct;
use App\Models\User;

class LoanProductPolicy
{
    /**
     * Determine whether the user can view the list of loan products.
     */
    public function viewAny(User $user): bool
    {
        return $user->canAccessAdminPanel();
    }

    /**
     * Determine whether the user can view the given loan product.
     */
    public function view(User $user, LoanProduct $loanProduct): bool
    {
        return $user->canAccessAdminPanel();
    }

    /**
     * Determine whether the user can create loan products.
     */
    public function create(User $user): bool
    {
        return $user->canAccessAdminPanel();
    }

    /**
     * Determine whether the user can update the given loan product.
     */
    public function update(User $user, LoanProduct $loanProduct): bool
    {
        return $user->canAccessAdminPanel();
    }

    /**
     * Determine whether the user can delete the given loan product.
     */
    public function delete(User $user, LoanProduct $loanProduct): bool
    {
        return $user->canAccessAdminPanel();
    }
}
