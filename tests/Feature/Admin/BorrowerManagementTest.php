<?php

use App\Enums\KycStatus;
use App\Enums\LoanStatus;
use App\Models\ActivityLog;
use App\Models\Borrower;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Payment;
use App\Models\User;

test('the borrower list exposes the abilities for every row', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $borrower = Borrower::factory()->create(['name' => 'Ada Lovelace']);

    $this->actingAs($superAdmin)
        ->get(route('admin.borrowers.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/borrowers/index')
            ->has('borrowers.data', 1)
            ->where('borrowers.data.0.name', 'Ada Lovelace')
            ->where('borrowers.data.0.kyc_status', KycStatus::Pending->value)
            ->where('borrowers.data.0.can.update', true)
            ->where('borrowers.data.0.can.delete', true)
        );
});

test('the borrower list reports each borrower portfolio', function () {
    $admin = User::factory()->admin()->create();
    $borrower = Borrower::factory()->create();

    $openLoan = Loan::factory()
        ->for($borrower)
        ->withStatus(LoanStatus::Active)
        ->create();

    LoanInstallment::factory()->create([
        'loan_id' => $openLoan->id,
        'sequence' => 1,
        'principal_due' => 100,
        'interest_due' => 20,
        'fee_due' => 5,
        'penalty_accrued' => 0,
    ]);
    LoanInstallment::factory()->create([
        'loan_id' => $openLoan->id,
        'sequence' => 2,
        'principal_due' => 50,
        'interest_due' => 10,
        'fee_due' => 0,
        'penalty_accrued' => 0,
    ]);

    $closedLoan = Loan::factory()
        ->for($borrower)
        ->withStatus(LoanStatus::Closed)
        ->create();

    LoanInstallment::factory()->create([
        'loan_id' => $closedLoan->id,
        'sequence' => 1,
        'principal_due' => 500,
        'interest_due' => 0,
        'fee_due' => 0,
        'penalty_accrued' => 0,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.borrowers.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('borrowers.data', 1)
            ->where('borrowers.data.0.loan_count', 2)
            ->where('borrowers.data.0.open_loan_count', 1)
            ->where('borrowers.data.0.outstanding', '185.00')
        );
});

test('the borrower register can be exported as CSV with the current filters', function () {
    $admin = User::factory()->admin()->create();
    $linked = User::factory()->create(['email' => 'portal@example.com']);

    Borrower::factory()
        ->forUser($linked)
        ->verified()
        ->create([
            'name' => 'Ada Lovelace',
            'phone' => '+1-555-0100',
            'email' => 'ada@example.com',
            'national_id' => '111222333',
        ]);

    Borrower::factory()->create([
        'name' => 'Grace Hopper',
        'email' => 'grace@example.com',
        'kyc_status' => KycStatus::Pending->value,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.borrowers.export', [
            'kyc_status' => KycStatus::Verified->value,
        ]));

    $response->assertOk();

    expect($response->headers->get('content-type'))->toContain('text/csv');

    $content = $response->streamedContent();

    expect($content)
        ->toContain('Name')
        ->toContain('KYC status')
        ->toContain('Ada Lovelace')
        ->toContain('+1-555-0100')
        ->toContain('ada@example.com')
        ->toContain('111222333')
        ->toContain('portal@example.com')
        ->toContain('Verified')
        ->not->toContain('Grace Hopper');
});

test('the borrower list can be filtered by kyc status and exposes counts', function () {
    $admin = User::factory()->admin()->create();
    Borrower::factory()->create(['kyc_status' => KycStatus::Pending->value]);
    Borrower::factory()->count(2)->create(['kyc_status' => KycStatus::Verified->value]);
    Borrower::factory()->create(['kyc_status' => KycStatus::Rejected->value]);

    $this->actingAs($admin)
        ->get(route('admin.borrowers.index', ['kyc_status' => KycStatus::Verified->value]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/borrowers/index')
            ->has('borrowers.data', 2)
            ->where('currentKycStatus', KycStatus::Verified->value)
            ->where('kycCounts.all', 4)
            ->where('kycCounts.pending', 1)
            ->where('kycCounts.verified', 2)
            ->where('kycCounts.rejected', 1)
        );
});

test('the borrower list can be searched by name, email or phone and counts respect the search', function () {
    $admin = User::factory()->admin()->create();
    Borrower::factory()->create([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'phone' => '+1-555-0100',
        'kyc_status' => KycStatus::Verified->value,
    ]);
    Borrower::factory()->create([
        'name' => 'Grace Hopper',
        'email' => 'grace@example.com',
        'phone' => '+1-555-0200',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.borrowers.index', ['search' => 'ada@example.com']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/borrowers/index')
            ->has('borrowers.data', 1)
            ->where('borrowers.data.0.name', 'Ada Lovelace')
            ->where('search', 'ada@example.com')
            ->where('kycCounts.all', 1)
            ->where('kycCounts.verified', 1)
        );
});

test('admins can register a borrower', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.borrowers.store'), [
            'name' => 'Grace Hopper',
            'national_id' => 'NID-123456',
            'phone' => '+1-555-0100',
            'email' => 'grace@example.com',
            'date_of_birth' => '1985-12-09',
            'address' => '1 Navy Yard, Arlington',
            'kyc_status' => KycStatus::Verified->value,
            'notes' => 'Priority customer.',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.borrowers.index'));

    $borrower = Borrower::where('national_id', 'NID-123456')->first();

    expect($borrower)->not->toBeNull()
        ->and($borrower->name)->toBe('Grace Hopper')
        ->and($borrower->kyc_status)->toBe(KycStatus::Verified);
});

test('a borrower may optionally be linked to a user account', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.borrowers.store'), [
            'name' => 'Linked Borrower',
            'phone' => '+1-555-0111',
            'kyc_status' => KycStatus::Pending->value,
            'user_id' => $user->id,
        ])
        ->assertSessionHasNoErrors();

    expect(Borrower::where('name', 'Linked Borrower')->first()?->user_id)->toBe($user->id);
});

test('registering a borrower validates the required fields and a unique national id', function () {
    $admin = User::factory()->admin()->create();
    Borrower::factory()->create(['national_id' => 'DUPLICATE']);

    $this->actingAs($admin)
        ->post(route('admin.borrowers.store'), [
            'name' => '',
            'phone' => '',
            'national_id' => 'DUPLICATE',
            'kyc_status' => 'not-a-status',
        ])
        ->assertSessionHasErrors(['name', 'phone', 'national_id', 'kyc_status']);
});

test('the borrower detail page shows their loans, payments and abilities', function () {
    $admin = User::factory()->admin()->create();
    $borrower = Borrower::factory()->verified()->create([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ]);
    $loan = Loan::factory()->withStatus(LoanStatus::Active)->create([
        'borrower_id' => $borrower->id,
        'principal' => 5000,
    ]);
    $payment = Payment::factory()->create([
        'loan_id' => $loan->id,
        'amount' => 500,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.borrowers.show', $borrower))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/borrowers/show')
            ->where('borrower.name', 'Ada Lovelace')
            ->where('borrower.email', 'ada@example.com')
            ->where('borrower.kyc_status', KycStatus::Verified->value)
            ->where('borrower.date_of_birth', $borrower->date_of_birth?->toDateString())
            ->where('summary.loan_count', 1)
            ->where('summary.open_loan_count', 1)
            ->where('summary.loans.0.id', $loan->id)
            ->where('summary.payments.0.id', $payment->id)
            ->where('summary.payment_count', 1)
            ->where('summary.upcoming_count', 0)
            ->where('can.update', true)
            ->where('can.delete', true)
            ->where('can.viewActivity', false)
        );
});

test('the borrower file reports totals beyond the visible windows', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $borrower = Borrower::factory()->create();
    $loan = Loan::factory()->withStatus(LoanStatus::Active)->create([
        'borrower_id' => $borrower->id,
    ]);

    LoanInstallment::factory()->count(7)->create(['loan_id' => $loan->id]);
    Payment::factory()->count(12)->create(['loan_id' => $loan->id]);

    foreach (range(1, 12) as $index) {
        ActivityLog::create([
            'user_id' => $superAdmin->id,
            'event' => ActivityLog::EVENT_UPDATED,
            'description' => "Borrower update {$index}.",
            'subject_type' => Borrower::class,
            'subject_id' => $borrower->id,
        ]);
    }

    $this->actingAs($superAdmin)
        ->get(route('admin.borrowers.show', $borrower))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('summary.upcoming', 5)
            ->where('summary.upcoming_count', 7)
            ->has('summary.payments', 10)
            ->where('summary.payment_count', 12)
            ->has('activity', 10)
            ->where('activityCount', fn (int $count): bool => $count >= 12)
        );
});

test('borrower activity is only exposed to super admins', function () {
    $admin = User::factory()->admin()->create();
    $superAdmin = User::factory()->superAdmin()->create();
    $borrower = Borrower::factory()->create();

    ActivityLog::create([
        'user_id' => $superAdmin->id,
        'event' => ActivityLog::EVENT_UPDATED,
        'description' => 'Borrower contact details updated.',
        'subject_type' => Borrower::class,
        'subject_id' => $borrower->id,
        'new_values' => ['phone' => '+1-555-0100'],
    ]);

    $this->actingAs($admin)
        ->get(route('admin.borrowers.show', $borrower))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('can.viewActivity', false)
            ->has('activity', 0)
            ->where('activityCount', 0)
        );

    $this->actingAs($superAdmin)
        ->get(route('admin.borrowers.show', $borrower))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('can.viewActivity', true)
            ->has('activity')
            ->where('activityCount', fn (int $count): bool => $count >= 2)
            ->where('activity.0.description', 'Borrower contact details updated.')
        );
});

test('the borrower detail page includes activity from their loans', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $borrower = Borrower::factory()->create();
    $loan = Loan::factory()->create(['borrower_id' => $borrower->id]);

    ActivityLog::create([
        'user_id' => $superAdmin->id,
        'event' => ActivityLog::EVENT_UPDATED,
        'description' => 'Loan status updated.',
        'subject_type' => Loan::class,
        'subject_id' => $loan->id,
        'new_values' => ['status' => LoanStatus::Approved->value],
    ]);

    $this->actingAs($superAdmin)
        ->get(route('admin.borrowers.show', $borrower))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('activity')
            ->where('activity.0.description', 'Loan status updated.')
        );
});

test('admins can update a borrower', function () {
    $admin = User::factory()->admin()->create();
    $borrower = Borrower::factory()->create();

    $this->actingAs($admin)
        ->put(route('admin.borrowers.update', $borrower), [
            'name' => 'Updated Borrower',
            'national_id' => $borrower->national_id,
            'phone' => '+1-555-0199',
            'email' => $borrower->email,
            'kyc_status' => KycStatus::Rejected->value,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.borrowers.index'));

    $borrower->refresh();

    expect($borrower->name)->toBe('Updated Borrower')
        ->and($borrower->kyc_status)->toBe(KycStatus::Rejected);
});

test('admins can update the kyc status of several borrowers at once', function () {
    $admin = User::factory()->admin()->create();

    $borrowers = Borrower::factory()
        ->count(3)
        ->create(['kyc_status' => KycStatus::Pending->value]);

    $selected = $borrowers->take(2);

    $this->actingAs($admin)
        ->patch(route('admin.borrowers.kyc-status'), [
            'borrowers' => $selected->pluck('id')->all(),
            'kyc_status' => KycStatus::Verified->value,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($selected[0]->refresh()->kyc_status)->toBe(KycStatus::Verified)
        ->and($selected[1]->refresh()->kyc_status)->toBe(KycStatus::Verified)
        ->and($borrowers[2]->refresh()->kyc_status)->toBe(KycStatus::Pending)
        ->and(ActivityLog::query()
            ->where('event', ActivityLog::EVENT_UPDATED)
            ->whereIn('subject_id', $selected->pluck('id'))
            ->where('subject_type', Borrower::class)
            ->count())->toBe(2);
});

test('admins can delete a borrower', function () {
    $admin = User::factory()->admin()->create();
    $borrower = Borrower::factory()->create();

    $this->actingAs($admin)
        ->delete(route('admin.borrowers.destroy', $borrower))
        ->assertRedirect(route('admin.borrowers.index'));

    expect($borrower->fresh())->toBeNull();
});
