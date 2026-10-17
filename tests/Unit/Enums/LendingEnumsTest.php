<?php

use App\Enums\InterestMethod;
use App\Enums\KycStatus;
use App\Enums\RepaymentFrequency;

test('interest methods expose their string values and labels', function () {
    expect(InterestMethod::Flat->value)->toBe('flat')
        ->and(InterestMethod::Flat->label())->toBe('Flat')
        ->and(InterestMethod::DecliningBalance->value)->toBe('declining_balance')
        ->and(InterestMethod::DecliningBalance->label())->toBe('Declining Balance');
});

test('repayment frequencies expose their string values and labels', function () {
    expect(RepaymentFrequency::Monthly->value)->toBe('monthly')
        ->and(RepaymentFrequency::Monthly->label())->toBe('Monthly')
        ->and(RepaymentFrequency::Weekly->label())->toBe('Weekly')
        ->and(RepaymentFrequency::Biweekly->label())->toBe('Biweekly');
});

test('repayment frequencies expose the number of periods per year', function () {
    expect(RepaymentFrequency::Monthly->periodsPerYear())->toBe(12)
        ->and(RepaymentFrequency::Weekly->periodsPerYear())->toBe(52)
        ->and(RepaymentFrequency::Biweekly->periodsPerYear())->toBe(26);
});

test('kyc statuses expose their string values and labels', function () {
    expect(KycStatus::Pending->value)->toBe('pending')
        ->and(KycStatus::Pending->label())->toBe('Pending')
        ->and(KycStatus::Verified->label())->toBe('Verified')
        ->and(KycStatus::Rejected->label())->toBe('Rejected');
});
