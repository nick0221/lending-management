<?php

use App\Models\Borrower;
use App\Models\User;

test('a borrower sees their notification preferences', function () {
    $user = User::factory()->create();
    Borrower::factory()->create([
        'user_id' => $user->id,
        'email' => 'borrower@example.com',
        'phone' => '555-0100',
        'notify_due_soon' => true,
        'notify_overdue' => true,
        'notify_receipts' => false,
        'notify_statements' => true,
        'notify_sms' => false,
    ]);

    $this->actingAs($user)
        ->get(route('notifications.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/notifications')
            ->where('preferences.email', 'borrower@example.com')
            ->where('preferences.phone', '555-0100')
            ->where('preferences.notify_due_soon', true)
            ->where('preferences.notify_overdue', true)
            ->where('preferences.notify_receipts', false)
            ->where('preferences.notify_statements', true)
            ->where('preferences.notify_sms', false)
            ->where('account.has_borrower_profile', true)
        );
});

test('a borrower can update their notification preferences', function () {
    $user = User::factory()->create();
    $borrower = Borrower::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->patch(route('notifications.update'), [
            'notify_due_soon' => '1',
            'notify_overdue' => '0',
            'notify_receipts' => '0',
            'notify_statements' => '1',
            'notify_sms' => '1',
        ])
        ->assertRedirect(route('notifications.edit'));

    $borrower->refresh();

    expect($borrower->notify_due_soon)->toBeTrue()
        ->and($borrower->notify_overdue)->toBeFalse()
        ->and($borrower->notify_receipts)->toBeFalse()
        ->and($borrower->notify_statements)->toBeTrue()
        ->and($borrower->notify_sms)->toBeTrue();
});

test('notification preferences must be boolean', function () {
    $user = User::factory()->create();
    Borrower::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->patch(route('notifications.update'), [
            'notify_due_soon' => 'perhaps',
            'notify_overdue' => '0',
            'notify_receipts' => '0',
            'notify_statements' => '0',
            'notify_sms' => '0',
        ])
        ->assertSessionHasErrors('notify_due_soon');
});

test('an account without a borrower profile cannot manage notifications', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('notifications.edit'))
        ->assertForbidden();

    $this->actingAs(User::factory()->create())
        ->get(route('notifications.edit'))
        ->assertForbidden();
});

test('guests are sent to the login page from notification settings', function () {
    $this->get(route('notifications.edit'))
        ->assertRedirect(route('login'));
});
