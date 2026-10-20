<?php

namespace App\Models;

use App\Enums\Role;
use App\Models\Concerns\AuditsActivity;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Role $role
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use AuditsActivity, HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * The model's default attribute values.
     *
     * Mirrors the database default so unsaved models expose the role before
     * they are persisted.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => Role::User->value,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Determine whether the user is a super admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === Role::SuperAdmin;
    }

    /**
     * Determine whether the user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    /**
     * Determine whether the user has the given role.
     */
    public function hasRole(Role $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Determine whether the user has any of the given roles.
     */
    public function hasAnyRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /**
     * Determine whether the user can access the administration panel.
     */
    public function canAccessAdminPanel(): bool
    {
        return $this->hasAnyRole(Role::SuperAdmin, Role::Admin);
    }

    /**
     * Get the roles this user may assign to the given account.
     *
     * A user may never change their own role, so only the account's current
     * role is returned when the target is the user themselves.
     *
     * @return array<int, Role>
     */
    public function assignableRolesFor(?User $target = null): array
    {
        if ($target !== null && $this->is($target)) {
            return [$this->role];
        }

        return $this->role->assignableRoles();
    }
}
