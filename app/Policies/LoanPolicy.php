<?php

namespace App\Policies;

use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Models\User;

class LoanPolicy
{
    /**
     * Determine whether the user can view the list of loans.
     */
    public function viewAny(User $user): bool
    {
        return $user->canAccessAdminPanel();
    }

    /**
     * Determine whether the user can view the given loan.
     */
    public function view(User $user, Loan $loan): bool
    {
        return $user->canAccessAdminPanel();
    }

    /**
     * Determine whether the user can originate loans.
     */
    public function create(User $user): bool
    {
        return $user->canAccessAdminPanel();
    }

    /**
     * Determine whether the user can update the given loan.
     */
    public function update(User $user, Loan $loan): bool
    {
        return $user->canAccessAdminPanel() && ! $loan->status->hasBeenDisbursed();
    }

    /**
     * Determine whether the user can approve the given loan.
     */
    public function approve(User $user, Loan $loan): bool
    {
        return $user->canAccessAdminPanel();
    }

    /**
     * Determine whether the user can reject the given loan.
     */
    public function reject(User $user, Loan $loan): bool
    {
        return $user->canAccessAdminPanel();
    }

    /**
     * Determine whether the user can disburse the given loan.
     */
    public function disburse(User $user, Loan $loan): bool
    {
        return $user->canAccessAdminPanel()
            && $loan->status === LoanStatus::Approved;
    }

    /**
     * Determine whether the user can record a payment against the loan.
     */
    public function recordPayment(User $user, Loan $loan): bool
    {
        return $user->canAccessAdminPanel() && $loan->status === LoanStatus::Active;
    }

    /**
     * Determine whether the user can flag the loan as defaulted.
     */
    public function markDefaulted(User $user, Loan $loan): bool
    {
        return $user->canAccessAdminPanel() && $loan->status === LoanStatus::Active;
    }

    /**
     * Determine whether the user can write off the loan.
     */
    public function writeOff(User $user, Loan $loan): bool
    {
        return $user->canAccessAdminPanel()
            && in_array($loan->status, [LoanStatus::Active, LoanStatus::Defaulted], true);
    }

    /**
     * Determine whether the user can restructure the loan.
     */
    public function restructure(User $user, Loan $loan): bool
    {
        return $user->canAccessAdminPanel()
            && in_array($loan->status, [LoanStatus::Active, LoanStatus::Defaulted], true);
    }

    /**
     * Determine whether the user can email overdue reminders in bulk.
     */
    public function sendReminders(User $user): bool
    {
        return $user->canAccessAdminPanel();
    }

    /**
     * Determine whether the user can delete the given loan.
     */
    public function delete(User $user, Loan $loan): bool
    {
        return $user->canAccessAdminPanel() && ! $loan->status->hasBeenDisbursed();
    }
}
