<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view the list of users.
     */
    public function viewAny(User $user): bool
    {
        return $user->canAccessAdminPanel();
    }

    /**
     * Determine whether the user can view the given user.
     */
    public function view(User $user, User $model): bool
    {
        return $user->canAccessAdminPanel();
    }

    /**
     * Determine whether the user can create users.
     */
    public function create(User $user): bool
    {
        return $user->canAccessAdminPanel();
    }

    /**
     * Determine whether the user can update the given user.
     *
     * Super admins manage every account. Admins may only manage their own
     * account and regular user accounts. Regular users may not use the policy
     * at all, even for their own account.
     */
    public function update(User $user, User $model): bool
    {
        if (! $user->canAccessAdminPanel()) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->is($model) || $model->hasRole(Role::User);
    }

    /**
     * Determine whether the user can delete the given user.
     *
     * Nobody may delete their own account from the admin panel. Admins may
     * only delete regular user accounts.
     */
    public function delete(User $user, User $model): bool
    {
        if (! $user->canAccessAdminPanel() || $user->is($model)) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $model->hasRole(Role::User);
    }

    /**
     * Determine whether the user may assign the given role to the given user.
     */
    public function assignRole(User $user, User $model, Role $role): bool
    {
        if (! $this->update($user, $model)) {
            return false;
        }

        return in_array($role, $user->assignableRolesFor($model), true);
    }
}
