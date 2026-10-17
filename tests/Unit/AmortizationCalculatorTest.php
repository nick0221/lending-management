<?php

use App\Enums\InterestMethod;
use App\Enums\RepaymentFrequency;
use App\Services\AmortizationCalculator;

test('flat interest splits the principal and interest evenly', function () {
    $rows = (new AmortizationCalculator)->calculate(
        principal: 12000,
        annualRatePercent: 12,
        interestMethod: InterestMethod::Flat,
        termMonths: 12,
        repaymentFrequency: RepaymentFrequency::Monthly,
    );

    expect($rows)->toHaveCount(12)
        ->and($rows[0]['principal_due'])->toBe(1000.0)
        ->and($rows[0]['interest_due'])->toBe(120.0)
        ->and($rows[11]['balance'])->toBe(0.0)
        ->and(collect($rows)->sum('principal_due'))->toEqualWithDelta(12000, 0.001)
        ->and(collect($rows)->sum('interest_due'))->toEqualWithDelta(1440, 0.001);
});

test('declining balance charges interest on the outstanding balance', function () {
    $rows = (new AmortizationCalculator)->calculate(
        principal: 12000,
        annualRatePercent: 12,
        interestMethod: InterestMethod::DecliningBalance,
        termMonths: 12,
        repaymentFrequency: RepaymentFrequency::Monthly,
    );

    expect($rows)->toHaveCount(12)
        ->and($rows[0]['interest_due'])->toBe(120.0)
        ->and($rows[0]['principal_due'])->toBe(946.19)
        ->and($rows[11]['balance'])->toBe(0.0)
        ->and(collect($rows)->sum('principal_due'))->toEqualWithDelta(12000, 0.001)
        ->and(collect($rows)->sum('interest_due'))->toEqualWithDelta(794.23, 0.001);
});

test('the processing fee is spread across the installments', function () {
    $rows = (new AmortizationCalculator)->calculate(
        principal: 12000,
        annualRatePercent: 12,
        interestMethod: InterestMethod::Flat,
        termMonths: 12,
        repaymentFrequency: RepaymentFrequency::Monthly,
        processingFeeRatePercent: 2,
    );

    expect($rows[0]['fee_due'])->toBe(20.0)
        ->and(collect($rows)->sum('fee_due'))->toEqualWithDelta(240, 0.001);
});

test('a weekly term converts the months into weekly periods', function () {
    $rows = (new AmortizationCalculator)->calculate(
        principal: 1200,
        annualRatePercent: 12,
        interestMethod: InterestMethod::Flat,
        termMonths: 1,
        repaymentFrequency: RepaymentFrequency::Weekly,
    );

    // One month is 52/12 ≈ 4.33 weeks, rounded to 4 weekly periods.
    expect($rows)->toHaveCount(4)
        ->and(collect($rows)->sum('principal_due'))->toEqualWithDelta(1200, 0.001);
});

test('a zero interest loan repays only the principal', function () {
    $rows = (new AmortizationCalculator)->calculate(
        principal: 1200,
        annualRatePercent: 0,
        interestMethod: InterestMethod::DecliningBalance,
        termMonths: 12,
        repaymentFrequency: RepaymentFrequency::Monthly,
    );

    expect(collect($rows)->sum('interest_due'))->toEqualWithDelta(0, 0.001)
        ->and(collect($rows)->sum('principal_due'))->toEqualWithDelta(1200, 0.001)
        ->and($rows[11]['balance'])->toBe(0.0);
});
