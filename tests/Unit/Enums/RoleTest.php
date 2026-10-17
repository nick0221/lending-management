<?php

use App\Enums\Role;

test('roles expose their string values', function () {
    expect(Role::SuperAdmin->value)->toBe('super_admin')
        ->and(Role::Admin->value)->toBe('admin')
        ->and(Role::User->value)->toBe('user');
});

test('roles expose human readable labels', function () {
    expect(Role::SuperAdmin->label())->toBe('Super Admin')
        ->and(Role::Admin->label())->toBe('Admin')
        ->and(Role::User->label())->toBe('User');
});

test('the role hierarchy ranks super admins above admins and users', function () {
    expect(Role::SuperAdmin->isAtLeast(Role::Admin))->toBeTrue()
        ->and(Role::SuperAdmin->isAtLeast(Role::User))->toBeTrue()
        ->and(Role::Admin->isAtLeast(Role::User))->toBeTrue()
        ->and(Role::Admin->isAtLeast(Role::SuperAdmin))->toBeFalse()
        ->and(Role::User->isAtLeast(Role::Admin))->toBeFalse();
});

test('only super admins may assign every role', function () {
    expect(Role::SuperAdmin->assignableRoles())->toBe(Role::cases())
        ->and(Role::Admin->assignableRoles())->toBe([Role::User])
        ->and(Role::User->assignableRoles())->toBe([Role::User]);
});
