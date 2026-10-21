<?php

use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Models\User;

test('admins can bulk approve pending loans and skip others', function () {
    $admin = User::factory()->admin()->create();
    $pending = Loan::factory()->pending()->count(2)->create();
    $approved = Loan::factory()->approved()->create();

    $ids = [...$pending->pluck('id')->all(), $approved->id];

    $this->actingAs($admin)
        ->from(route('admin.loans.index'))
        ->post(route('admin.loans.bulk-approve'), ['loans' => $ids])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.loans.index'));

    foreach ($pending as $loan) {
        $loan->refresh();

        expect($loan->status)->toBe(LoanStatus::Approved)
            ->and($loan->approved_at)->not->toBeNull()
            ->and($loan->installments()->count())->toBeGreaterThan(0);
    }

    expect($approved->fresh()->status)->toBe(LoanStatus::Approved);
});

test('the loan list exposes bulk approval only for pending loans', function () {
    $admin = User::factory()->admin()->create();
    Loan::factory()->pending()->create();
    Loan::factory()->approved()->create();

    $this->actingAs($admin)
        ->get(route('admin.loans.index', ['status' => LoanStatus::Pending->value]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/loans/index')
            ->where('loans.data.0.can.approve', true)
        );

    $this->actingAs($admin)
        ->get(route('admin.loans.index', ['status' => LoanStatus::Approved->value]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('loans.data.0.can.approve', false)
        );
});

test('bulk approval requires at least one loan', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.loans.bulk-approve'), [])
        ->assertSessionHasErrors('loans');
});

test('regular users cannot bulk approve loans', function () {
    $user = User::factory()->create();
    $loan = Loan::factory()->pending()->create();

    $this->actingAs($user)
        ->post(route('admin.loans.bulk-approve'), ['loans' => [$loan->id]])
        ->assertForbidden();

    expect($loan->fresh()->status)->toBe(LoanStatus::Pending);
});
