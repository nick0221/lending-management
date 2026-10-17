<?php

use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Models\User;
use App\Policies\LoanPolicy;

test('only administrators may manage loans', function () {
    $policy = new LoanPolicy;
    $loan = Loan::factory()->create();

    $superAdmin = User::factory()->superAdmin()->create();
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    expect($policy->viewAny($superAdmin))->toBeTrue()
        ->and($policy->view($superAdmin, $loan))->toBeTrue()
        ->and($policy->create($superAdmin))->toBeTrue()
        ->and($policy->approve($superAdmin, $loan))->toBeTrue()
        ->and($policy->reject($superAdmin, $loan))->toBeTrue()
        ->and($policy->delete($superAdmin, $loan))->toBeTrue()
        ->and($policy->viewAny($admin))->toBeTrue()
        ->and($policy->view($admin, $loan))->toBeTrue()
        ->and($policy->create($admin))->toBeTrue()
        ->and($policy->approve($admin, $loan))->toBeTrue()
        ->and($policy->reject($admin, $loan))->toBeTrue()
        ->and($policy->delete($admin, $loan))->toBeTrue()
        ->and($policy->viewAny($user))->toBeFalse()
        ->and($policy->view($user, $loan))->toBeFalse()
        ->and($policy->create($user))->toBeFalse()
        ->and($policy->approve($user, $loan))->toBeFalse()
        ->and($policy->reject($user, $loan))->toBeFalse()
        ->and($policy->delete($user, $loan))->toBeFalse();
});

test('only approved loans may be disbursed and disbursed loans are immutable', function () {
    $policy = new LoanPolicy;

    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $approved = Loan::factory()->approved()->create();
    $pending = Loan::factory()->pending()->create();
    $active = Loan::factory()->withStatus(LoanStatus::Active)->create();

    expect($policy->disburse($admin, $approved))->toBeTrue()
        ->and($policy->disburse($admin, $pending))->toBeFalse()
        ->and($policy->disburse($admin, $active))->toBeFalse()
        ->and($policy->disburse($user, $approved))->toBeFalse()
        ->and($policy->delete($admin, $approved))->toBeTrue()
        ->and($policy->delete($admin, $active))->toBeFalse()
        ->and($policy->update($admin, $active))->toBeFalse();
});

test('payments may only be recorded against active loans by administrators', function () {
    $policy = new LoanPolicy;

    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $active = Loan::factory()->withStatus(LoanStatus::Active)->create();
    $pending = Loan::factory()->pending()->create();
    $closed = Loan::factory()->withStatus(LoanStatus::Closed)->create();

    expect($policy->recordPayment($admin, $active))->toBeTrue()
        ->and($policy->recordPayment($admin, $pending))->toBeFalse()
        ->and($policy->recordPayment($admin, $closed))->toBeFalse()
        ->and($policy->recordPayment($user, $active))->toBeFalse();
});

test('arrears actions are limited to administrators and open loans', function () {
    $policy = new LoanPolicy;

    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $active = Loan::factory()->withStatus(LoanStatus::Active)->create();
    $defaulted = Loan::factory()->withStatus(LoanStatus::Defaulted)->create();
    $closed = Loan::factory()->withStatus(LoanStatus::Closed)->create();

    expect($policy->markDefaulted($admin, $active))->toBeTrue()
        ->and($policy->markDefaulted($admin, $defaulted))->toBeFalse()
        ->and($policy->markDefaulted($admin, $closed))->toBeFalse()
        ->and($policy->markDefaulted($user, $active))->toBeFalse()
        ->and($policy->writeOff($admin, $active))->toBeTrue()
        ->and($policy->writeOff($admin, $defaulted))->toBeTrue()
        ->and($policy->writeOff($admin, $closed))->toBeFalse()
        ->and($policy->writeOff($user, $active))->toBeFalse()
        ->and($policy->restructure($admin, $active))->toBeTrue()
        ->and($policy->restructure($admin, $defaulted))->toBeTrue()
        ->and($policy->restructure($admin, $closed))->toBeFalse()
        ->and($policy->restructure($user, $active))->toBeFalse();
});
