<?php

namespace App\Services;

use App\Enums\LoanStatus;
use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Borrower;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Builds the role matrix from the same policies that guard the application.
 * Each cell asks the gate whether the role may perform the ability, so the
 * page cannot drift away from the policy layer.
 */
class RoleMatrix
{
    /**
     * Get the grouped abilities with the result for every role.
     *
     * @return array<int, array{
     *     title: string,
     *     abilities: array<int, array{
     *         label: string,
     *         description: string,
     *         roles: array<string, bool>
     *     }>
     * }>
     */
    public function groups(): array
    {
        $groups = $this->definitions();

        foreach ($groups as &$group) {
            foreach ($group['abilities'] as &$ability) {
                $ability['roles'] = [];

                foreach (Role::cases() as $role) {
                    $actor = $this->user($role, 100 + $role->rank());

                    $ability['roles'][$role->value] = Gate::forUser($actor)->allows(
                        $ability['ability'],
                        $ability['arguments'],
                    );
                }

                unset($ability['ability'], $ability['arguments']);
            }
        }

        return $groups;
    }

    /**
     * The ability definitions, grouped for the page.
     *
     * @return array<int, array{
     *     title: string,
     *     abilities: array<int, array{
     *         label: string,
     *         description: string,
     *         ability: string,
     *         arguments: array<int, mixed>
     *     }>
     * }>
     */
    private function definitions(): array
    {
        return [
            [
                'title' => 'Borrowers',
                'abilities' => [
                    $this->ability('View borrowers', 'Open the borrower register and borrower files.', 'viewAny', [Borrower::class]),
                    $this->ability('View a borrower', 'See a borrower profile, their loans and notifications.', 'view', [new Borrower]),
                    $this->ability('Create borrowers', 'Register a new borrower.', 'create', [Borrower::class]),
                    $this->ability('Edit borrowers', 'Update contact, KYC and notification details.', 'update', [new Borrower]),
                    $this->ability('Delete borrowers', 'Remove a borrower and their records.', 'delete', [new Borrower]),
                ],
            ],
            [
                'title' => 'Loan products',
                'abilities' => [
                    $this->ability('View products', 'Open the product catalogue.', 'viewAny', [LoanProduct::class]),
                    $this->ability('View a product', 'See the terms and usage of a product.', 'view', [new LoanProduct]),
                    $this->ability('Create products', 'Add a new product and its terms.', 'create', [LoanProduct::class]),
                    $this->ability('Edit products', 'Change rates, fees, limits or availability.', 'update', [new LoanProduct]),
                    $this->ability('Delete products', 'Remove a product from the catalogue.', 'delete', [new LoanProduct]),
                ],
            ],
            [
                'title' => 'Loans',
                'abilities' => [
                    $this->ability('View loans', 'Open the loan queue and loan files.', 'viewAny', [Loan::class]),
                    $this->ability('View a loan', 'See a loan schedule, payments and activity.', 'view', [new Loan]),
                    $this->ability('Originate loans', 'Create a loan from an approved product.', 'create', [Loan::class]),
                    $this->ability('Edit pending loans', 'Change an application before it is disbursed.', 'update', [new Loan(['status' => LoanStatus::Pending])]),
                    $this->ability('Approve pending loans', 'Allow a pending application to move forward.', 'approve', [new Loan(['status' => LoanStatus::Pending])]),
                    $this->ability('Reject pending loans', 'Decline a pending application.', 'reject', [new Loan(['status' => LoanStatus::Pending])]),
                    $this->ability('Disburse loans', 'Release funds against an approved loan.', 'disburse', [new Loan(['status' => LoanStatus::Approved])]),
                    $this->ability('Record payments', 'Apply money to the oldest installments first.', 'recordPayment', [new Loan(['status' => LoanStatus::Active])]),
                    $this->ability('Mark defaulted', 'Flag an active loan as defaulted.', 'markDefaulted', [new Loan(['status' => LoanStatus::Active])]),
                    $this->ability('Restructure loans', 'Re-amortise the outstanding principal.', 'restructure', [new Loan(['status' => LoanStatus::Active])]),
                    $this->ability('Write off loans', 'Close an active or defaulted loan as uncollectable.', 'writeOff', [new Loan(['status' => LoanStatus::Active])]),
                    $this->ability('Delete pending loans', 'Remove a loan before it is disbursed.', 'delete', [new Loan(['status' => LoanStatus::Pending])]),
                    $this->ability('Send bulk reminders', 'Queue overdue notices for selected loans.', 'sendReminders', [Loan::class]),
                ],
            ],
            [
                'title' => 'Users',
                'abilities' => [
                    $this->ability('View users', 'Open the user register.', 'viewAny', [User::class]),
                    $this->ability('Create users', 'Add an account and assign its first role.', 'create', [User::class]),
                    $this->ability('Edit regular users', 'Change a regular user account.', 'update', [$this->user(Role::User, 10)]),
                    $this->ability('Edit admin accounts', 'Change another administrator account.', 'update', [$this->user(Role::Admin, 11)]),
                    $this->ability('Delete regular users', 'Remove a regular user account.', 'delete', [$this->user(Role::User, 10)]),
                    $this->ability('Delete admin accounts', 'Remove another administrator account.', 'delete', [$this->user(Role::Admin, 11)]),
                    $this->ability('Assign the user role', 'Give an account the regular user role.', 'assignRole', [$this->user(Role::User, 10), Role::User]),
                    $this->ability('Assign the admin role', 'Give an account the administrator role.', 'assignRole', [$this->user(Role::User, 10), Role::Admin]),
                    $this->ability('Assign the super admin role', 'Give an account the highest role.', 'assignRole', [$this->user(Role::User, 10), Role::SuperAdmin]),
                ],
            ],
            [
                'title' => 'Audit trail',
                'abilities' => [
                    $this->ability('View the audit trail', 'Browse every recorded administrative change.', 'viewAny', [ActivityLog::class]),
                    $this->ability('View an audit entry', 'See the actor, time and before/after values.', 'view', [new ActivityLog]),
                ],
            ],
        ];
    }

    /**
     * Build an unsaved user with a stable key so policies that compare the
     * actor with the target do not treat every unsaved account as the same.
     */
    private function user(Role $role, int $id): User
    {
        $user = new User;
        $user->setAttribute('id', $id);
        $user->role = $role;

        return $user;
    }

    /**
     * @param  array<int, mixed>  $arguments
     * @return array{
     *     label: string,
     *     description: string,
     *     ability: string,
     *     arguments: array<int, mixed>
     * }
     */
    private function ability(string $label, string $description, string $ability, array $arguments): array
    {
        return compact('label', 'description', 'ability', 'arguments');
    }
}
