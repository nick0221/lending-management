<?php

use App\Models\User;

test('the role matrix is evaluated from the policies', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.roles.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/roles/index')
            ->has('roles', 3)
            ->has('groups', 5)
            ->where('counts.super_admin', 0)
            ->where('counts.admin', 1)
            ->where('counts.user', 0)
            ->where('groups.0.title', 'Borrowers')
            ->where('groups.0.abilities.0.label', 'View borrowers')
            ->where('groups.0.abilities.0.roles.super_admin', true)
            ->where('groups.0.abilities.0.roles.admin', true)
            ->where('groups.0.abilities.0.roles.user', false)
        );
});

test('the role matrix reflects the narrower user and audit abilities', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.roles.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('groups.3.abilities.3.label', 'Edit admin accounts')
            ->where('groups.3.abilities.3.roles.super_admin', true)
            ->where('groups.3.abilities.3.roles.admin', false)
            ->where('groups.3.abilities.8.label', 'Assign the super admin role')
            ->where('groups.3.abilities.8.roles.super_admin', true)
            ->where('groups.3.abilities.8.roles.admin', false)
            ->where('groups.4.abilities.0.label', 'View the audit trail')
            ->where('groups.4.abilities.0.roles.super_admin', true)
            ->where('groups.4.abilities.0.roles.admin', false)
            ->where('groups.4.abilities.0.roles.user', false)
        );
});

test('regular users cannot open the role matrix', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.roles.index'))
        ->assertForbidden();
});
