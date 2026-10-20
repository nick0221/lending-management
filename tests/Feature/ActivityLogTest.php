<?php

use App\Enums\PaymentMethod;
use App\Models\ActivityLog;
use App\Models\Borrower;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\User;
use App\Services\RepaymentService;

test('creating a model writes a created entry with the actor', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin);

    $borrower = Borrower::factory()->create(['name' => 'Audited Borrower']);

    $log = ActivityLog::query()
        ->where('subject_type', Borrower::class)
        ->where('event', ActivityLog::EVENT_CREATED)
        ->latest('id')
        ->firstOrFail();

    expect($log->user_id)->toBe($superAdmin->id)
        ->and($log->subject_id)->toBe($borrower->id)
        ->and($log->description)->toBe("Borrower #{$borrower->id} created")
        ->and($log->new_values['name'])->toBe('Audited Borrower')
        ->and($log->new_values)->not->toHaveKey('updated_at')
        ->and($log->ip_address)->not->toBeNull();
});

test('updating a model only records the changed attributes', function () {
    $borrower = Borrower::factory()->create([
        'name' => 'Before',
        'phone' => '+1-111',
        'notes' => null,
    ]);

    $borrower->update(['name' => 'After', 'notes' => 'Called borrower']);

    $log = ActivityLog::query()
        ->where('subject_type', Borrower::class)
        ->where('event', ActivityLog::EVENT_UPDATED)
        ->latest('id')
        ->firstOrFail();

    expect($log->old_values)->toMatchArray(['name' => 'Before', 'notes' => null])
        ->and($log->new_values)->toMatchArray(['name' => 'After', 'notes' => 'Called borrower'])
        ->and($log->new_values)->not->toHaveKey('phone');
});

test('deleting a model records a snapshot of the old values', function () {
    $borrower = Borrower::factory()->create(['name' => 'Removed Borrower']);

    $borrower->delete();

    $log = ActivityLog::query()
        ->where('subject_type', Borrower::class)
        ->where('event', ActivityLog::EVENT_DELETED)
        ->latest('id')
        ->firstOrFail();

    expect($log->old_values['name'])->toBe('Removed Borrower')
        ->and($log->new_values)->toBeNull();
});

test('sensitive attributes are never written to the audit trail', function () {
    $user = User::factory()->create(['name' => 'Original']);

    $user->update(['name' => 'Renamed', 'password' => 'new-password']);

    $log = ActivityLog::query()
        ->where('subject_type', User::class)
        ->where('event', ActivityLog::EVENT_UPDATED)
        ->latest('id')
        ->firstOrFail();

    expect($log->new_values)->toHaveKey('name')
        ->and($log->new_values)->not->toHaveKey('password')
        ->and($log->new_values)->not->toHaveKey('two_factor_secret');
});

test('loans and payments are audited', function () {
    $loan = activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 10, 'fee' => 0, 'due_date' => '2026-07-01'],
    ]);

    expect(ActivityLog::query()->where('subject_type', Loan::class)->exists())->toBeTrue();

    $actor = User::factory()->admin()->create();

    app(RepaymentService::class)->recordPayment($loan, $actor, 10, PaymentMethod::Cash);

    expect(ActivityLog::query()->where('subject_type', Payment::class)->exists())->toBeTrue();
});

test('successful sign ins are recorded', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $log = ActivityLog::query()
        ->where('event', ActivityLog::EVENT_LOGIN)
        ->latest('id')
        ->firstOrFail();

    expect($log->user_id)->toBe($user->id)
        ->and($log->description)->toContain($user->email);
});

test('failed sign in attempts are recorded', function () {
    $this->post(route('login.store'), [
        'email' => 'ghost@example.com',
        'password' => 'wrong-password',
    ]);

    $log = ActivityLog::query()
        ->where('event', ActivityLog::EVENT_LOGIN_FAILED)
        ->latest('id')
        ->firstOrFail();

    expect($log->new_values['email'])->toBe('ghost@example.com')
        ->and($log->user_id)->toBeNull();
});

test('sign outs are recorded', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('logout'));

    expect(
        ActivityLog::query()
            ->where('event', ActivityLog::EVENT_LOGOUT)
            ->where('user_id', $user->id)
            ->exists(),
    )->toBeTrue();
});

test('only super admins can browse the audit trail', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    Borrower::factory()->create();

    $this->get(route('admin.activity.index'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.activity.index'))
        ->assertForbidden();

    $this->actingAs(User::factory()->create())
        ->get(route('admin.activity.index'))
        ->assertForbidden();

    $this->actingAs($superAdmin)
        ->get(route('admin.activity.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/activity/index')
            ->has('logs.data')
            ->has('events', 6)
            ->has('types', 6)
            ->has('users'));
});

test('the audit trail can be filtered by event, record type, user and date', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $borrower = Borrower::factory()->create(['name' => 'Filtered Borrower']);

    $this->post(route('login.store'), [
        'email' => $superAdmin->email,
        'password' => 'password',
    ]);

    $this->actingAs($superAdmin);

    $this->get(route('admin.activity.index', ['event' => 'login']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('logs.data', 1)
            ->where('logs.data.0.event', ActivityLog::EVENT_LOGIN));

    $this->get(route('admin.activity.index', ['type' => 'Borrower']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('logs.data.0.subject_type', 'Borrower')
            ->where('logs.data.0.subject_id', $borrower->id));

    $this->get(route('admin.activity.index', ['user' => $superAdmin->id, 'event' => 'login']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('logs.data', 1));

    $this->get(route('admin.activity.index', ['from' => today()->toDateString()]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('logs.data', 3));

    $this->get(route('admin.activity.index', ['to' => today()->subDay()->toDateString()]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('logs.data', 0));
});
