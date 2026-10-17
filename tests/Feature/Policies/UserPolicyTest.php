<?php

use App\Enums\Role;
use App\Models\User;
use App\Policies\UserPolicy;

test('super admins may update any account', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $policy = new UserPolicy;

    expect($policy->update($superAdmin, User::factory()->superAdmin()->create()))->toBeTrue()
        ->and($policy->update($superAdmin, User::factory()->admin()->create()))->toBeTrue()
        ->and($policy->update($superAdmin, User::factory()->create()))->toBeTrue();
});

test('admins may only update themselves and regular users', function () {
    $admin = User::factory()->admin()->create();
    $policy = new UserPolicy;

    expect($policy->update($admin, $admin))->toBeTrue()
        ->and($policy->update($admin, User::factory()->create()))->toBeTrue()
        ->and($policy->update($admin, User::factory()->admin()->create()))->toBeFalse()
        ->and($policy->update($admin, User::factory()->superAdmin()->create()))->toBeFalse();
});

test('regular users may not update accounts through the policy', function () {
    $user = User::factory()->create();
    $policy = new UserPolicy;

    expect($policy->update($user, User::factory()->create()))->toBeFalse()
        ->and($policy->update($user, $user))->toBeFalse();
});

test('nobody may delete their own account', function () {
    $policy = new UserPolicy;
    $superAdmin = User::factory()->superAdmin()->create();
    $admin = User::factory()->admin()->create();

    expect($policy->delete($superAdmin, $superAdmin))->toBeFalse()
        ->and($policy->delete($admin, $admin))->toBeFalse();
});

test('super admins may delete any other account', function () {
    $policy = new UserPolicy;
    $superAdmin = User::factory()->superAdmin()->create();

    expect($policy->delete($superAdmin, User::factory()->create()))->toBeTrue()
        ->and($policy->delete($superAdmin, User::factory()->admin()->create()))->toBeTrue()
        ->and($policy->delete($superAdmin, User::factory()->superAdmin()->create()))->toBeTrue();
});

test('admins may only delete regular users', function () {
    $admin = User::factory()->admin()->create();
    $policy = new UserPolicy;

    expect($policy->delete($admin, User::factory()->create()))->toBeTrue()
        ->and($policy->delete($admin, User::factory()->admin()->create()))->toBeFalse()
        ->and($policy->delete($admin, User::factory()->superAdmin()->create()))->toBeFalse();
});

test('role assignment respects the assignable role boundary', function () {
    $policy = new UserPolicy;
    $superAdmin = User::factory()->superAdmin()->create();
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    expect($policy->assignRole($superAdmin, $user, Role::SuperAdmin))->toBeTrue()
        ->and($policy->assignRole($superAdmin, $user, Role::User))->toBeTrue()
        ->and($policy->assignRole($admin, $user, Role::User))->toBeTrue()
        ->and($policy->assignRole($admin, $user, Role::Admin))->toBeFalse()
        ->and($policy->assignRole($admin, $user, Role::SuperAdmin))->toBeFalse()
        ->and($policy->assignRole($user, User::factory()->create(), Role::User))->toBeFalse();
});

test('users may not change their own role', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $policy = new UserPolicy;

    expect($policy->assignRole($superAdmin, $superAdmin, Role::SuperAdmin))->toBeTrue()
        ->and($policy->assignRole($superAdmin, $superAdmin, Role::Admin))->toBeFalse();
});
