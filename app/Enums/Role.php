<?php

namespace App\Enums;

enum Role: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case User = 'user';

    /**
     * Get the human readable label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Admin',
            self::User => 'User',
        };
    }

    /**
     * Determine whether the role is equal to or higher than the given role.
     *
     * The hierarchy is SuperAdmin > Admin > User.
     */
    public function isAtLeast(Role $role): bool
    {
        return $this->rank() >= $role->rank();
    }

    /**
     * Get the numeric rank of the role within the hierarchy.
     */
    public function rank(): int
    {
        return match ($this) {
            self::SuperAdmin => 3,
            self::Admin => 2,
            self::User => 1,
        };
    }

    /**
     * Get the roles that the given role is allowed to assign to other users.
     *
     * @return array<int, Role>
     */
    public function assignableRoles(): array
    {
        return match ($this) {
            self::SuperAdmin => self::cases(),
            self::Admin, self::User => [self::User],
        };
    }
}
