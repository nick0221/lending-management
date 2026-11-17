<?php

use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Enums\LoanStatus;
use App\Models\ApprovalRequest;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\User;
use App\Services\ApprovalService;
use Illuminate\Validation\ValidationException;

/**
 * Raise a reversal request through the endpoint so the policy and validation
 * layers are exercised alongside the service.
 */
function requestReversal(User $requester, Loan $loan, Payment $payment, string $reason = 'Recorded twice.'): ApprovalRequest
{
    test()->actingAs($requester)
        ->post(route('admin.loans.payments.reverse', [$loan, $payment]), [
            'reason' => $reason,
        ])
        ->assertRedirect();

    return ApprovalRequest::query()->where('payment_id', $payment->id)->sole();
}

test('a reversal request changes nothing until another administrator approves it', function () {
    $requester = User::factory()->admin()->create();
    $approver = User::factory()->admin()->create();

    $loan = Loan::factory()->withStatus(LoanStatus::Active)->create();
    $payment = Payment::factory()->for($loan)->create(['amount' => 250]);

    $approval = requestReversal($requester, $loan, $payment);

    expect($payment->fresh()->reversed_at)->toBeNull()
        ->and($approval->status)->toBe(ApprovalStatus::Pending)
        ->and($approval->amount)->toBe('250.00');

    test()->actingAs($approver)
        ->from(route('admin.approvals.index'))
        ->post(route('admin.approvals.approve', $approval), [
            'note' => 'Confirmed with the branch.',
        ])
        ->assertRedirect(route('admin.approvals.index'));

    $approval->refresh();

    expect($payment->fresh()->reversed_at)->not->toBeNull()
        ->and($payment->fresh()->reversed_by)->toBe($approver->id)
        ->and($payment->fresh()->reversal_reason)->toBe('Recorded twice.')
        ->and($approval->status)->toBe(ApprovalStatus::Approved)
        ->and($approval->reviewed_by)->toBe($approver->id)
        ->and($approval->review_note)->toBe('Confirmed with the branch.');
});

test('a requester can never approve their own request', function () {
    $requester = User::factory()->admin()->create();

    $loan = Loan::factory()->withStatus(LoanStatus::Active)->create();
    $payment = Payment::factory()->for($loan)->create();

    $approval = requestReversal($requester, $loan, $payment);

    test()->actingAs($requester)
        ->post(route('admin.approvals.approve', $approval))
        ->assertForbidden();

    expect($payment->fresh()->reversed_at)->toBeNull()
        ->and($approval->fresh()->status)->toBe(ApprovalStatus::Pending);
});

test('the service also refuses self-approval when the policy is bypassed', function () {
    $requester = User::factory()->admin()->create();

    $loan = Loan::factory()->withStatus(LoanStatus::Active)->create();
    $payment = Payment::factory()->for($loan)->create();

    $approval = requestReversal($requester, $loan, $payment);

    expect(fn () => app(ApprovalService::class)->approve($approval, $requester))
        ->toThrow(ValidationException::class);

    expect($payment->fresh()->reversed_at)->toBeNull();
});

test('rejecting a request leaves the payment untouched', function () {
    $requester = User::factory()->admin()->create();
    $reviewer = User::factory()->superAdmin()->create();

    $loan = Loan::factory()->withStatus(LoanStatus::Active)->create();
    $payment = Payment::factory()->for($loan)->create();

    $approval = requestReversal($requester, $loan, $payment);

    test()->actingAs($reviewer)
        ->post(route('admin.approvals.reject', $approval), [
            'note' => 'The receipt matches our records.',
        ])
        ->assertRedirect();

    $approval->refresh();

    expect($payment->fresh()->reversed_at)->toBeNull()
        ->and($approval->status)->toBe(ApprovalStatus::Rejected)
        ->and($approval->reviewed_by)->toBe($reviewer->id)
        ->and($approval->review_note)->toBe('The receipt matches our records.');
});

test('a decided request cannot be approved a second time', function () {
    $requester = User::factory()->admin()->create();
    $approver = User::factory()->admin()->create();

    $loan = Loan::factory()->withStatus(LoanStatus::Active)->create();
    $payment = Payment::factory()->for($loan)->create();

    $approval = requestReversal($requester, $loan, $payment);

    test()->actingAs($approver)
        ->post(route('admin.approvals.approve', $approval))
        ->assertRedirect();

    $reversedAt = $payment->fresh()->reversed_at;

    test()->actingAs($approver)
        ->post(route('admin.approvals.approve', $approval))
        ->assertForbidden();

    expect($payment->fresh()->reversed_at->toIso8601String())->toBe($reversedAt->toIso8601String());
});

test('a payment cannot have two reversal requests awaiting approval', function () {
    $requester = User::factory()->admin()->create();

    $loan = Loan::factory()->withStatus(LoanStatus::Active)->create();
    $payment = Payment::factory()->for($loan)->create();

    requestReversal($requester, $loan, $payment);

    test()->actingAs($requester)
        ->post(route('admin.loans.payments.reverse', [$loan, $payment]), [
            'reason' => 'Again.',
        ])
        ->assertSessionHasErrors('approval');

    expect(ApprovalRequest::query()->count())->toBe(1);
});

test('a loan cannot have two write-off requests awaiting approval', function () {
    $requester = User::factory()->admin()->create();

    $loan = Loan::factory()->withStatus(LoanStatus::Active)->create();

    test()->actingAs($requester)
        ->post(route('admin.loans.write-off', $loan), ['reason' => 'Uncollectable.'])
        ->assertRedirect();

    test()->actingAs($requester)
        ->post(route('admin.loans.write-off', $loan), ['reason' => 'Still uncollectable.'])
        ->assertSessionHasErrors('approval');

    expect(ApprovalRequest::query()->count())->toBe(1)
        ->and($loan->fresh()->status)->toBe(LoanStatus::Active);
});

test('regular users cannot see the approval queue', function () {
    $user = User::factory()->create();

    test()->actingAs($user)
        ->get(route('admin.approvals.index'))
        ->assertForbidden();
});

test('regular users cannot decide a request', function () {
    $requester = User::factory()->admin()->create();
    $user = User::factory()->create();

    $loan = Loan::factory()->withStatus(LoanStatus::Active)->create();
    $payment = Payment::factory()->for($loan)->create();

    $approval = requestReversal($requester, $loan, $payment);

    test()->actingAs($user)
        ->post(route('admin.approvals.approve', $approval))
        ->assertForbidden();
});

test('the queue marks the requester and lets other administrators decide', function () {
    $requester = User::factory()->admin()->create();
    $colleague = User::factory()->admin()->create();

    $loan = Loan::factory()->withStatus(LoanStatus::Active)->create();
    $payment = Payment::factory()->for($loan)->create(['amount' => 90]);

    requestReversal($requester, $loan, $payment);

    test()->actingAs($requester)
        ->get(route('admin.approvals.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/approvals/index')
            ->has('approvals.data', 1)
            ->where('approvals.data.0.type', ApprovalType::PaymentReversal->value)
            ->where('approvals.data.0.is_requester', true)
            ->where('approvals.data.0.can_decide', false)
            ->where('counts.pending', 1)
        );

    test()->actingAs($colleague)
        ->get(route('admin.approvals.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('approvals.data.0.is_requester', false)
            ->where('approvals.data.0.can_decide', true)
        );
});

test('the queue tabs separate decided requests from pending ones', function () {
    $requester = User::factory()->admin()->create();
    $approver = User::factory()->admin()->create();

    $loan = Loan::factory()->withStatus(LoanStatus::Active)->create();
    $approvedPayment = Payment::factory()->for($loan)->create();
    $pendingPayment = Payment::factory()->for($loan)->create();

    $approved = requestReversal($requester, $loan, $approvedPayment);
    requestReversal($requester, $loan, $pendingPayment);

    test()->actingAs($approver)
        ->post(route('admin.approvals.approve', $approved))
        ->assertRedirect();

    test()->actingAs($approver)
        ->get(route('admin.approvals.index', ['tab' => 'approved']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('approvals.data', 1)
            ->where('approvals.data.0.id', $approved->id)
            ->where('counts.pending', 1)
            ->where('counts.approved', 1)
        );
});

test('a pending write-off hides the write-off action and shows the banner data', function () {
    $requester = User::factory()->admin()->create();

    $loan = Loan::factory()->withStatus(LoanStatus::Active)->create();

    test()->actingAs($requester)
        ->post(route('admin.loans.write-off', $loan), ['reason' => 'Uncollectable.'])
        ->assertRedirect();

    test()->actingAs($requester)
        ->get(route('admin.loans.show', $loan))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('can.write_off', false)
            ->where('writeOffRequest.requested_by', $requester->name)
            ->has('writeOffRequest.approval_id')
        );
});

test('a pending reversal disables the reversal action on the loan file', function () {
    $requester = User::factory()->admin()->create();

    $loan = Loan::factory()->withStatus(LoanStatus::Active)->create();
    $payment = Payment::factory()->for($loan)->create();

    requestReversal($requester, $loan, $payment);

    test()->actingAs($requester)
        ->get(route('admin.loans.show', $loan))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('payments.0.can_reverse', false)
            ->where('payments.0.reversal_pending', true)
        );
});
