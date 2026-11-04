<?php

use App\Enums\LoanStatus;
use App\Models\CollectionContact;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\User;

/**
 * Give the loan one overdue installment so its arrears and outstanding figures
 * are predictable.
 */
function overdueInstallment(Loan $loan, string $principal, int $daysLate = 10): LoanInstallment
{
    return LoanInstallment::factory()->for($loan)->create([
        'sequence' => 1,
        'due_date' => today()->subDays($daysLate)->toDateString(),
        'principal_due' => $principal,
        'interest_due' => 0,
        'fee_due' => 0,
        'penalty_accrued' => 0,
    ]);
}

test('the collections queue lists only overdue open loans', function () {
    $admin = User::factory()->admin()->create();

    $activeOverdue = Loan::factory()->withStatus(LoanStatus::Active)->create(['days_past_due' => 10]);
    overdueInstallment($activeOverdue, '5000.00');

    $defaultedOverdue = Loan::factory()->withStatus(LoanStatus::Defaulted)->create(['days_past_due' => 5]);
    overdueInstallment($defaultedOverdue, '1000.00', 5);

    $current = Loan::factory()->withStatus(LoanStatus::Active)->create(['days_past_due' => 0]);
    overdueInstallment($current, '750.00');

    Loan::factory()->withStatus(LoanStatus::Closed)->create(['days_past_due' => 30]);
    Loan::factory()->withStatus(LoanStatus::Pending)->create(['days_past_due' => 12]);

    $this->actingAs($admin)
        ->get(route('admin.collections.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/collections/index')
            ->has('loans.data', 2)
            ->where('loans.data', fn ($data): bool => $data->pluck('id')->all() === [
                $activeOverdue->id,
                $defaultedOverdue->id,
            ])
            ->where('loans.data.0.arrears', '5000.00')
            ->where('loans.data.0.outstanding', '5000.00')
            ->where('loans.data.0.days_past_due', 10)
            ->where('counts.all', 2)
            ->where('summary.loans', 2)
            ->where('summary.arrears', '6000.00')
        );
});

test('the queue tabs and filters scope the overdue book', function () {
    $admin = User::factory()->admin()->create();
    $superAdmin = User::factory()->superAdmin()->create();

    $promised = Loan::factory()->withStatus(LoanStatus::Active)->create([
        'days_past_due' => 10,
        'collection_officer_id' => $admin->id,
    ]);

    CollectionContact::factory()->for($promised)->promised('2500.00')->create([
        'user_id' => $admin->id,
        'follow_up_at' => today()->addWeek()->toDateString(),
    ]);

    $followUp = Loan::factory()->withStatus(LoanStatus::Defaulted)->create(['days_past_due' => 45]);

    CollectionContact::factory()->for($followUp)->create([
        'user_id' => $superAdmin->id,
        'outcome' => 'contacted',
        'follow_up_at' => today()->toDateString(),
    ]);

    $aging = Loan::factory()->withStatus(LoanStatus::Active)->create([
        'days_past_due' => 100,
        'collection_officer_id' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.collections.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('loans.data', 3)
            ->where('counts.all', 3)
            ->where('counts.unassigned', 1)
            ->where('counts.promised', 1)
            ->where('counts.follow_up', 1)
            ->where('summary.promised', 1)
            ->where('summary.unassigned', 1)
            ->where('officers', fn ($officers): bool => $officers->pluck('value')->sort()->values()->all() === [
                $admin->id,
                $superAdmin->id,
            ])
        );

    $this->actingAs($admin)
        ->get(route('admin.collections.index', ['tab' => 'promised']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('loans.data', 1)
            ->where('loans.data.0.id', $promised->id)
            ->where('loans.data.0.promise.amount', '2500.00')
            ->where('loans.data.0.promise.days_until', 7)
            ->where('loans.data.0.promise.missed', false)
            ->where('loans.data.0.collection_officer', $admin->name)
        );

    $this->actingAs($admin)
        ->get(route('admin.collections.index', ['tab' => 'unassigned']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('loans.data', 1)
            ->where('loans.data.0.id', $followUp->id)
        );

    $this->actingAs($admin)
        ->get(route('admin.collections.index', ['tab' => 'follow_up']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('loans.data', 1)
            ->where('loans.data.0.id', $followUp->id)
            ->where('loans.data.0.next_follow_up', today()->toDateString())
            ->where('loans.data.0.follow_up_overdue', false)
            ->where('loans.data.0.last_contact.outcome_label', 'Contacted')
        );

    $this->actingAs($admin)
        ->get(route('admin.collections.index', ['officer' => $admin->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('loans.data', 2)
            ->where('loans.data', fn ($data): bool => $data->pluck('id')->sort()->values()->all() === [
                $promised->id,
                $aging->id,
            ])
        );

    $this->actingAs($admin)
        ->get(route('admin.collections.index', ['bucket' => 'over_90']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('loans.data', 1)
            ->where('loans.data.0.id', $aging->id)
        );

    $this->actingAs($admin)
        ->get(route('admin.collections.index', ['search' => $followUp->borrower->phone]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('loans.data', 1)
            ->where('loans.data.0.id', $followUp->id)
        );
});

test('a lapsed follow-up is shown as overdue', function () {
    $admin = User::factory()->admin()->create();
    $loan = Loan::factory()->withStatus(LoanStatus::Active)->create(['days_past_due' => 20]);

    CollectionContact::factory()->for($loan)->create([
        'follow_up_at' => today()->subDays(3)->toDateString(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.collections.index', ['tab' => 'follow_up']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('loans.data', 1)
            ->where('loans.data.0.next_follow_up', today()->subDays(3)->toDateString())
            ->where('loans.data.0.follow_up_overdue', true)
        );
});

test('a collection officer can be assigned to and cleared from a loan', function () {
    $admin = User::factory()->admin()->create();
    $loan = Loan::factory()->withStatus(LoanStatus::Active)->create();

    $this->actingAs($admin)
        ->patch(route('admin.collections.officer', $loan), ['officer_id' => $admin->id])
        ->assertRedirect();

    expect($loan->fresh()->collection_officer_id)->toBe($admin->id);

    $this->actingAs($admin)
        ->patch(route('admin.collections.officer', $loan), ['officer_id' => null])
        ->assertRedirect();

    expect($loan->fresh()->collection_officer_id)->toBeNull();
});

test('a collection officer must be a staff member', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    $loan = Loan::factory()->withStatus(LoanStatus::Active)->create();

    $this->actingAs($admin)
        ->patch(route('admin.collections.officer', $loan), ['officer_id' => $user->id])
        ->assertSessionHasErrors('officer_id');

    expect($loan->fresh()->collection_officer_id)->toBeNull();
});

test('officers cannot be assigned by regular users', function () {
    $user = User::factory()->create();
    $loan = Loan::factory()->withStatus(LoanStatus::Active)->create();

    $this->actingAs($user)
        ->patch(route('admin.collections.officer', $loan), ['officer_id' => $user->id])
        ->assertForbidden();

    expect($loan->fresh()->collection_officer_id)->toBeNull();
});

test('officers cannot be assigned to a closed loan', function () {
    $admin = User::factory()->admin()->create();
    $loan = Loan::factory()->withStatus(LoanStatus::Closed)->create();

    $this->actingAs($admin)
        ->patch(route('admin.collections.officer', $loan), ['officer_id' => $admin->id])
        ->assertForbidden();

    expect($loan->fresh()->collection_officer_id)->toBeNull();
});

test('the queue offers the contact channels and outcomes needed to log a call', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.collections.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('collectionChannels', 5)
            ->where('collectionChannels.0.value', 'call')
            ->where('collectionChannels.0.label', 'Phone call')
            ->has('collectionOutcomes', 6)
            ->where('collectionOutcomes.0.value', 'promised')
            ->where('collectionOutcomes.0.label', 'Promised to pay')
        );
});

test('the collections queue exports the filtered work list as a CSV', function () {
    $admin = User::factory()->admin()->create();

    $promised = Loan::factory()->withStatus(LoanStatus::Active)->create([
        'days_past_due' => 10,
        'collection_officer_id' => $admin->id,
    ]);
    overdueInstallment($promised, '5000.00');
    $promised->borrower->update([
        'name' => 'Ada Promised',
        'phone' => '+1-555-0100',
    ]);

    CollectionContact::factory()->for($promised)->promised('2500.00')->create([
        'user_id' => $admin->id,
        'follow_up_at' => today()->addWeek()->toDateString(),
    ]);

    $other = Loan::factory()->withStatus(LoanStatus::Defaulted)->create(['days_past_due' => 40]);
    overdueInstallment($other, '1000.00', 40);

    $response = $this->actingAs($admin)
        ->get(route('admin.collections.export', ['tab' => 'promised']));

    $response->assertOk();

    expect($response->headers->get('content-type'))->toContain('text/csv');

    $content = $response->streamedContent();

    expect($content)
        ->toContain('Promise amount')
        ->toContain('Next follow-up')
        ->toContain('Ada Promised')
        ->toContain('+1-555-0100')
        ->toContain('2500.00')
        ->toContain($admin->name)
        ->not->toContain($other->borrower->name);

    expect(array_filter(explode("\n", trim($content))))->toHaveCount(2);
});
