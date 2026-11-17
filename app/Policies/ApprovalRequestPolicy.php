<?php

namespace App\Policies;

use App\Models\ApprovalRequest;
use App\Models\User;

class ApprovalRequestPolicy
{
    /**
     * Determine whether the user can view the approval queue.
     */
    public function viewAny(User $user): bool
    {
        return $user->canAccessAdminPanel();
    }

    /**
     * Determine whether the user can view the given request.
     */
    public function view(User $user, ApprovalRequest $approvalRequest): bool
    {
        return $user->canAccessAdminPanel();
    }

    /**
     * Determine whether the user can approve or reject the request.
     *
     * Four-eyes rule: the person who raised the request can never decide it.
     */
    public function decide(User $user, ApprovalRequest $approvalRequest): bool
    {
        return $user->canAccessAdminPanel()
            && $approvalRequest->isPending()
            && (int) $approvalRequest->requested_by !== (int) $user->id;
    }
}
