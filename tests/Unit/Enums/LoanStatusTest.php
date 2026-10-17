<?php

use App\Enums\LoanStatus;

test('loan statuses expose their string values and labels', function () {
    expect(LoanStatus::Pending->value)->toBe('pending')
        ->and(LoanStatus::Pending->label())->toBe('Pending')
        ->and(LoanStatus::WrittenOff->value)->toBe('written_off')
        ->and(LoanStatus::WrittenOff->label())->toBe('Written Off');
});

test('a pending loan may be approved or rejected', function () {
    expect(LoanStatus::Pending->canTransitionTo(LoanStatus::Approved))->toBeTrue()
        ->and(LoanStatus::Pending->canTransitionTo(LoanStatus::Rejected))->toBeTrue()
        ->and(LoanStatus::Pending->canTransitionTo(LoanStatus::Disbursed))->toBeFalse()
        ->and(LoanStatus::Pending->canTransitionTo(LoanStatus::Closed))->toBeFalse();
});

test('a rejected loan is final', function () {
    expect(LoanStatus::Rejected->isFinal())->toBeTrue()
        ->and(LoanStatus::Closed->isFinal())->toBeTrue()
        ->and(LoanStatus::WrittenOff->isFinal())->toBeTrue()
        ->and(LoanStatus::Pending->isFinal())->toBeFalse()
        ->and(LoanStatus::Active->isFinal())->toBeFalse();
});

test('the approved lifecycle moves through disbursed and active', function () {
    expect(LoanStatus::Approved->canTransitionTo(LoanStatus::Disbursed))->toBeTrue()
        ->and(LoanStatus::Disbursed->canTransitionTo(LoanStatus::Active))->toBeTrue()
        ->and(LoanStatus::Active->canTransitionTo(LoanStatus::Closed))->toBeTrue()
        ->and(LoanStatus::Active->canTransitionTo(LoanStatus::Defaulted))->toBeTrue()
        ->and(LoanStatus::Defaulted->canTransitionTo(LoanStatus::WrittenOff))->toBeTrue()
        ->and(LoanStatus::Draft->canTransitionTo(LoanStatus::Pending))->toBeTrue();
});
