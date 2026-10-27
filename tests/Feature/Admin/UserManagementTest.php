<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

test('the user list exposes the abilities for every row', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/users/index')
            ->has('users.data', 1)
            ->where('users.data.0.role', Role::SuperAdmin->value)
            ->where('users.data.0.two_factor_enabled', false)
            ->where('users.data.0.can.update', true)
            ->where('users.data.0.can.delete', false)
        );
});

test('the user list reports whether two-factor authentication is enabled', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $user = User::factory()->create([
        'two_factor_confirmed_at' => now(),
    ]);

    $this->actingAs($superAdmin)
        ->get(route('admin.users.index', ['search' => $user->email]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.id', $user->id)
            ->where('users.data.0.two_factor_enabled', true)
        );
});

test('the user list can be searched by name or email', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    User::factory()->admin()->create([
        'name' => 'Needle Person',
        'email' => 'needle@example.com',
    ]);
    User::factory()->create([
        'name' => 'Someone Else',
        'email' => 'someone@example.com',
    ]);

    $this->actingAs($superAdmin)
        ->get(route('admin.users.index', ['search' => 'needle']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.email', 'needle@example.com')
            ->where('search', 'needle')
            ->where('roleCounts.admin', 1)
            ->where('roleCounts.user', 0)
            ->where('roleCounts.all', 1)
        );
});

test('the user list can be filtered by role', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $admin = User::factory()->admin()->create();
    User::factory()->create();

    $this->actingAs($superAdmin)
        ->get(route('admin.users.index', ['role' => Role::Admin->value]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.id', $admin->id)
            ->where('currentRole', Role::Admin->value)
            ->where('roleCounts.super_admin', 1)
            ->where('roleCounts.admin', 1)
            ->where('roleCounts.user', 1)
            ->where('roleCounts.all', 3)
        );
});

test('creating a user sends a verification email', function () {
    Notification::fake();

    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->post(route('admin.users.store'), [
            'name' => 'New User',
            'email' => 'new-user@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => Role::User->value,
        ])
        ->assertSessionHasNoErrors();

    $user = User::where('email', 'new-user@example.com')->firstOrFail();

    Notification::assertSentTo($user, VerifyEmail::class);
});

test('changing a user email resets verification and sends a new link', function () {
    Notification::fake();

    $superAdmin = User::factory()->superAdmin()->create();
    $user = User::factory()->create();

    $this->actingAs($superAdmin)
        ->put(route('admin.users.update', $user), [
            'name' => $user->name,
            'email' => 'changed@example.com',
            'role' => Role::User->value,
        ])
        ->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->email_verified_at)->toBeNull();

    Notification::assertSentTo($user, VerifyEmail::class);
});

test('admins can resend a verification link', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $user = User::factory()->unverified()->create();

    $this->actingAs($admin)
        ->post(route('admin.users.verification', $user))
        ->assertRedirect();

    Notification::assertSentTo($user, VerifyEmail::class);
});

test('verified users do not receive another verification link', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.users.verification', $user))
        ->assertRedirect();

    Notification::assertNotSentTo($user, VerifyEmail::class);
});

test('admins can send a password reset link', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.users.password-reset', $user))
        ->assertRedirect();

    Notification::assertSentTo($user, ResetPassword::class);
});

test('admins cannot send recovery emails to another admin', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.users.verification', $otherAdmin))
        ->assertForbidden();

    $this->actingAs($admin)
        ->post(route('admin.users.password-reset', $otherAdmin))
        ->assertForbidden();

    Notification::assertNothingSent();
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
