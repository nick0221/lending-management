<?php

namespace App\Listeners;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

/**
 * Records sign in, sign out and failed sign in attempts in the audit trail.
 */
class RecordAuthenticationActivity
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * Handle a successful sign in.
     */
    public function login(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $this->auditLogger->log(
            ActivityLog::EVENT_LOGIN,
            sprintf('Signed in as %s', $event->user->email),
            $event->user,
            user: $event->user,
        );
    }

    /**
     * Handle a sign out.
     */
    public function logout(Logout $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $this->auditLogger->log(
            ActivityLog::EVENT_LOGOUT,
            sprintf('Signed out %s', $event->user->email),
            $event->user,
            user: $event->user,
        );
    }

    /**
     * Handle a failed sign in attempt.
     */
    public function failed(Failed $event): void
    {
        $email = $event->credentials['email'] ?? null;

        $this->auditLogger->log(
            ActivityLog::EVENT_LOGIN_FAILED,
            sprintf('Failed sign in attempt for %s', $email ?? 'an unknown account'),
            $event->user instanceof User ? $event->user : null,
            newValues: ['email' => $email],
        );
    }
}
