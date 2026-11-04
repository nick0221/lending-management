<?php

use App\Enums\LoanStatus;
use App\Models\CollectionContact;
use App\Models\Loan;
use App\Models\User;

test('a contact and promise can be logged against an active loan', function () {
    $admin = User::factory()->admin()->create();
    $loan = Loan::factory()->withStatus(LoanStatus::Active)->create();
    $promiseDate = today()->addWeek()->toDateString();

    $this->actingAs($admin)
        ->post(route('admin.loans.collections.store', $loan), [
            'channel' => 'call',
            'outcome' => 'promised',
            'contacted_at' => now()->toDateTimeString(),
            'notes' => 'Borrower will settle after payday.',
            'promised_amount' => '2500.00',
            'promised_at' => $promiseDate,
        ])
        ->assertRedirect();

    $contact = CollectionContact::query()->sole();

    expect($contact->loan_id)->toBe($loan->id)
        ->and($contact->user_id)->toBe($admin->id)
        ->and($contact->channel->value)->toBe('call')
        ->and($contact->outcome->value)->toBe('promised')
        ->and($contact->promised_amount)->toBe('2500.00')
        ->and($contact->promised_at?->toDateString())->toBe($promiseDate)
        ->and($contact->follow_up_at?->toDateString())->toBe($promiseDate);
});

test('a promise requires an amount and a date', function () {
    $admin = User::factory()->admin()->create();
    $loan = Loan::factory()->withStatus(LoanStatus::Active)->create();

    $this->actingAs($admin)
        ->post(route('admin.loans.collections.store', $loan), [
            'channel' => 'call',
            'outcome' => 'promised',
        ])
        ->assertSessionHasErrors(['promised_amount', 'promised_at']);

    expect(CollectionContact::query()->count())->toBe(0);
});

test('a non-promise outcome drops any promise details', function () {
    $admin = User::factory()->admin()->create();
    $loan = Loan::factory()->withStatus(LoanStatus::Active)->create();

    $this->actingAs($admin)
        ->post(route('admin.loans.collections.store', $loan), [
            'channel' => 'sms',
            'outcome' => 'no_answer',
            'promised_amount' => '999.00',
            'promised_at' => today()->addWeek()->toDateString(),
        ])
        ->assertRedirect();

    $contact = CollectionContact::query()->sole();

    expect($contact->promised_amount)->toBeNull()
        ->and($contact->promised_at)->toBeNull();
});

test('contacts cannot be logged against a closed loan', function () {
    $admin = User::factory()->admin()->create();
    $loan = Loan::factory()->withStatus(LoanStatus::Closed)->create();

    $this->actingAs($admin)
        ->post(route('admin.loans.collections.store', $loan), [
            'channel' => 'call',
            'outcome' => 'contacted',
        ])
        ->assertForbidden();

    expect(CollectionContact::query()->count())->toBe(0);
});

test('regular users cannot log contacts', function () {
    $user = User::factory()->create();
    $loan = Loan::factory()->withStatus(LoanStatus::Active)->create();

    $this->actingAs($user)
        ->post(route('admin.loans.collections.store', $loan), [
            'channel' => 'call',
            'outcome' => 'contacted',
        ])
        ->assertForbidden();
});

test('the loan file shows the contact history and open promise', function () {
    $admin = User::factory()->admin()->create();
    $loan = Loan::factory()->withStatus(LoanStatus::Active)->create();

    CollectionContact::factory()->for($loan)->promised('2500.00')->create([
        'user_id' => $admin->id,
        'channel' => 'call',
        'notes' => 'Will pay after payday.',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.loans.show', $loan))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/loans/show')
            ->where('can.log_contact', true)
            ->has('collectionContacts', 1)
            ->where('collectionContacts.0.channel', 'call')
            ->where('collectionContacts.0.channel_label', 'Phone call')
            ->where('collectionContacts.0.outcome', 'promised')
            ->where('collectionContacts.0.notes', 'Will pay after payday.')
            ->where('collectionContacts.0.recorded_by', $admin->name)
            ->where('collectionPromise.amount', '2500.00')
            ->where('collectionPromise.days_until', 7)
            ->has('collectionChannels', 5)
            ->has('collectionOutcomes', 6)
        );
});

test('the loan file accepts the collections tab', function () {
    $admin = User::factory()->admin()->create();
    $loan = Loan::factory()->withStatus(LoanStatus::Active)->create();

    $this->actingAs($admin)
        ->get(route('admin.loans.show', ['loan' => $loan, 'tab' => 'collections']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/loans/show')
            ->where('currentTab', 'collections')
        );
});

test('a promise whose date has passed is not shown as open', function () {
    $admin = User::factory()->admin()->create();
    $loan = Loan::factory()->withStatus(LoanStatus::Active)->create();

    CollectionContact::factory()->for($loan)->promised('500.00', today()->subDay()->toDateString())->create();

    $this->actingAs($admin)
        ->get(route('admin.loans.show', $loan))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('collectionContacts', 1)
            ->where('collectionPromise', null)
        );
});
