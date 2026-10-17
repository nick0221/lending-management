<?php

use App\Models\Borrower;
use App\Models\LoanProduct;
use App\Models\User;
use App\Policies\BorrowerPolicy;
use App\Policies\LoanProductPolicy;

test('only administrators may manage borrowers', function () {
    $policy = new BorrowerPolicy;
    $borrower = Borrower::factory()->create();

    $superAdmin = User::factory()->superAdmin()->create();
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    expect($policy->viewAny($superAdmin))->toBeTrue()
        ->and($policy->view($superAdmin, $borrower))->toBeTrue()
        ->and($policy->create($superAdmin))->toBeTrue()
        ->and($policy->update($superAdmin, $borrower))->toBeTrue()
        ->and($policy->delete($superAdmin, $borrower))->toBeTrue()
        ->and($policy->viewAny($admin))->toBeTrue()
        ->and($policy->update($admin, $borrower))->toBeTrue()
        ->and($policy->delete($admin, $borrower))->toBeTrue()
        ->and($policy->viewAny($user))->toBeFalse()
        ->and($policy->view($user, $borrower))->toBeFalse()
        ->and($policy->create($user))->toBeFalse()
        ->and($policy->update($user, $borrower))->toBeFalse()
        ->and($policy->delete($user, $borrower))->toBeFalse();
});

test('only administrators may manage loan products', function () {
    $policy = new LoanProductPolicy;
    $loanProduct = LoanProduct::factory()->create();

    $superAdmin = User::factory()->superAdmin()->create();
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    expect($policy->viewAny($superAdmin))->toBeTrue()
        ->and($policy->update($superAdmin, $loanProduct))->toBeTrue()
        ->and($policy->viewAny($admin))->toBeTrue()
        ->and($policy->delete($admin, $loanProduct))->toBeTrue()
        ->and($policy->viewAny($user))->toBeFalse()
        ->and($policy->view($user, $loanProduct))->toBeFalse()
        ->and($policy->create($user))->toBeFalse()
        ->and($policy->update($user, $loanProduct))->toBeFalse()
        ->and($policy->delete($user, $loanProduct))->toBeFalse();
});
