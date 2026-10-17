<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('the user list exposes the abilities for every row', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/users/index')
            ->has('users.data', 1)
            ->where('users.data.0.role', Role::SuperAdmin->value)
            ->where('users.data.0.can.update', true)
            ->where('users.data.0.can.delete', false)
        );
});

test('super admins can create a super admin', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->post(route('admin.users.store'), [
            'name' => 'New Super',
            'email' => 'new-super@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => Role::SuperAdmin->value,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.users.index'));

    expect(User::where('email', 'new-super@example.com')->first()?->role)
        ->toBe(Role::SuperAdmin);
});

test('admins can create regular users', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'New User',
            'email' => 'new-user@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => Role::User->value,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.users.index'));

    expect(User::where('email', 'new-user@example.com')->first()?->role)
        ->toBe(Role::User);
});

test('admins cannot create super admins', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'Escalated User',
            'email' => 'escalated@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => Role::SuperAdmin->value,
        ])
        ->assertForbidden();

    expect(User::where('email', 'escalated@example.com')->exists())->toBeFalse();
});

test('creating a user requires a unique email and a password', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->post(route('admin.users.store'), [
            'name' => 'Missing Password',
            'email' => $superAdmin->email,
            'role' => Role::User->value,
        ])
        ->assertSessionHasErrors(['email', 'password']);
});

test('admins can update regular users', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->put(route('admin.users.update', $user), [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'role' => Role::User->value,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.users.index'));

    $user->refresh();

    expect($user->name)->toBe('Updated Name')
        ->and($user->email)->toBe('updated@example.com');
});

test('admins cannot update other admins', function () {
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put(route('admin.users.update', $otherAdmin), [
            'name' => 'Hijacked',
            'email' => $otherAdmin->email,
            'role' => Role::Admin->value,
        ])
        ->assertForbidden();

    expect($otherAdmin->refresh()->name)->not->toBe('Hijacked');
});

test('super admins can change a user role', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $user = User::factory()->create();

    $this->actingAs($superAdmin)
        ->put(route('admin.users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'role' => Role::Admin->value,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.users.index'));

    expect($user->refresh()->role)->toBe(Role::Admin);
});

test('users cannot change their own role', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->put(route('admin.users.update', $superAdmin), [
            'name' => $superAdmin->name,
            'email' => $superAdmin->email,
            'role' => Role::Admin->value,
        ])
        ->assertForbidden();

    expect($superAdmin->refresh()->role)->toBe(Role::SuperAdmin);
});

test('updating a user can change their password', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $user = User::factory()->create();

    $this->actingAs($superAdmin)
        ->put(route('admin.users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'role' => Role::User->value,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertSessionHasNoErrors();

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});

test('admins can delete regular users', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $user))
        ->assertRedirect(route('admin.users.index'));

    expect($user->fresh())->toBeNull();
});

test('admins cannot delete other admins', function () {
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $otherAdmin))
        ->assertForbidden();

    expect($otherAdmin->fresh())->not->toBeNull();
});

test('nobody can delete their own account from the admin panel', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->delete(route('admin.users.destroy', $superAdmin))
        ->assertForbidden();

    expect($superAdmin->fresh())->not->toBeNull();
});
