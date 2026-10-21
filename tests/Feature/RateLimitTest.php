<?php

use App\Models\Loan;
use App\Models\User;
use App\Services\Settings;
use Illuminate\Support\Facades\Notification;

/**
 * Set one endpoint limit through the persisted settings.
 */
function setRateLimit(string $key, int $limit): void
{
    $settings = app(Settings::class);
    $settings->current()->update(["{$key}_rate_limit" => $limit]);
    $settings->flush();
}

/**
 * Create an active loan whose borrower can receive statements.
 */
function throttledLoan(): Loan
{
    $loan = activeLoanWithDatedSchedule([
        ['principal' => 100, 'interest' => 0, 'fee' => 0, 'due_date' => '2026-07-01'],
    ]);

    $loan->borrower->update(['email' => 'borrower@example.com']);

    return $loan;
}

test('money endpoints are throttled per user', function () {
    Notification::fake();
    setRateLimit('money', 2);

    $admin = User::factory()->admin()->create();
    $loan = throttledLoan();

    $this->actingAs($admin)
        ->post(route('admin.loans.statement', $loan))
        ->assertRedirect(route('admin.loans.show', $loan));

    $this->actingAs($admin)
        ->post(route('admin.loans.statement', $loan))
        ->assertRedirect(route('admin.loans.show', $loan));

    $this->actingAs($admin)
        ->post(route('admin.loans.statement', $loan))
        ->assertStatus(429)
        ->assertHeader('Retry-After');
});

test('money endpoints share one bucket per user', function () {
    Notification::fake();
    setRateLimit('money', 1);

    $admin = User::factory()->admin()->create();
    $loan = throttledLoan();

    $this->actingAs($admin)
        ->post(route('admin.loans.statement', $loan))
        ->assertRedirect();

    $this->actingAs($admin)
        ->post(route('admin.loans.mark-default', $loan))
        ->assertStatus(429);

    expect($loan->fresh()->status->value)->toBe('active');
});

test('another user keeps their own money allowance', function () {
    Notification::fake();
    setRateLimit('money', 1);

    $firstAdmin = User::factory()->admin()->create();
    $secondAdmin = User::factory()->admin()->create();
    $loan = throttledLoan();

    $this->actingAs($firstAdmin)
        ->post(route('admin.loans.statement', $loan))
        ->assertRedirect();

    $this->actingAs($firstAdmin)
        ->post(route('admin.loans.statement', $loan))
        ->assertStatus(429);

    $this->actingAs($secondAdmin)
        ->post(route('admin.loans.statement', $loan))
        ->assertRedirect();
});

test('report downloads are throttled', function () {
    setRateLimit('reports', 1);

    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->get(route('admin.reports.download', 'summary'))
        ->assertOk();

    $this->actingAs($superAdmin)
        ->get(route('admin.reports.download', 'summary'))
        ->assertStatus(429)
        ->assertHeader('Retry-After');
});
