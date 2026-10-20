import { Form, router } from '@inertiajs/react';
import {
    AlertTriangle,
    Ban,
    Check,
    Mail,
    MoreHorizontal,
    Trash2,
    Wrench,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { DisburseLoanDialog } from '@/components/admin/disburse-loan-dialog';
import { RecordPaymentDialog } from '@/components/admin/record-payment-dialog';
import { RestructureLoanDialog } from '@/components/admin/restructure-loan-dialog';
import { ConfirmDeleteDialog } from '@/components/confirm-delete';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    approve as approveLoan,
    destroy as destroyLoan,
    markDefault as markLoanDefault,
    reject as rejectLoan,
    statement as loanStatement,
    writeOff as writeOffLoan,
} from '@/routes/admin/loans';
import type {
    DisbursementMethodOption,
    Loan,
    LoanAbilities,
    LoanInstallment,
    PaymentBreakdown,
    PaymentMethodOption,
} from '@/types';

/**
 * The loan file action bar. Decisions and money movements stay visible; the
 * rarer or destructive actions live behind a single overflow menu.
 */
export function LoanActions({
    loan,
    outstanding,
    breakdown,
    installments,
    can,
    disbursementMethods,
    paymentMethods,
}: {
    loan: Loan;
    outstanding: string;
    breakdown: PaymentBreakdown;
    installments: LoanInstallment[];
    can: LoanAbilities;
    disbursementMethods: DisbursementMethodOption[];
    paymentMethods: PaymentMethodOption[];
}) {
    const [restructureOpen, setRestructureOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);

    const hasSecondaryActions =
        can.restructure ||
        can.statement ||
        can.default ||
        can.write_off ||
        can.delete;

    return (
        <>
            <div className="flex flex-wrap items-center gap-2">
                {can.approve && loan.status === 'pending' && (
                    <Form
                        {...approveLoan.form(loan.id)}
                        options={{ preserveScroll: true }}
                    >
                        {({ processing }) => (
                            <Button
                                type="submit"
                                disabled={processing}
                                data-test="approve-loan-button"
                            >
                                <Check /> Approve
                            </Button>
                        )}
                    </Form>
                )}

                {can.reject && loan.status === 'pending' && (
                    <Form
                        {...rejectLoan.form(loan.id)}
                        options={{ preserveScroll: true }}
                    >
                        {({ processing }) => (
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={processing}
                                data-test="reject-loan-button"
                            >
                                <X /> Reject
                            </Button>
                        )}
                    </Form>
                )}

                {can.disburse && (
                    <DisburseLoanDialog
                        loan={loan}
                        methods={disbursementMethods}
                    />
                )}

                {can.pay && (
                    <RecordPaymentDialog
                        loan={loan}
                        methods={paymentMethods}
                        outstanding={outstanding}
                        breakdown={breakdown}
                        installments={installments}
                    />
                )}

                {hasSecondaryActions && (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="outline"
                                aria-label="More loan actions"
                            >
                                <MoreHorizontal /> More
                            </Button>
                        </DropdownMenuTrigger>

                        <DropdownMenuContent align="end" className="w-52">
                            {can.restructure && (
                                <DropdownMenuItem
                                    onSelect={() => setRestructureOpen(true)}
                                    data-test="restructure-loan-button"
                                >
                                    <Wrench /> Restructure
                                </DropdownMenuItem>
                            )}

                            {can.statement && (
                                <DropdownMenuItem
                                    onSelect={() =>
                                        router.post(
                                            loanStatement.url(loan.id),
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                    data-test="email-statement-button"
                                >
                                    <Mail /> Email statement
                                </DropdownMenuItem>
                            )}

                            {(can.default || can.write_off) && (
                                <DropdownMenuSeparator />
                            )}

                            {can.default && (
                                <DropdownMenuItem
                                    onSelect={() =>
                                        router.post(
                                            markLoanDefault.url(loan.id),
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                    data-test="mark-default-loan-button"
                                >
                                    <AlertTriangle /> Mark defaulted
                                </DropdownMenuItem>
                            )}

                            {can.write_off && (
                                <DropdownMenuItem
                                    variant="destructive"
                                    onSelect={() =>
                                        router.post(
                                            writeOffLoan.url(loan.id),
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                    data-test="write-off-loan-button"
                                >
                                    <Ban /> Write off
                                </DropdownMenuItem>
                            )}

                            {can.delete && (
                                <>
                                    <DropdownMenuSeparator />

                                    <DropdownMenuItem
                                        variant="destructive"
                                        onSelect={(event) => {
                                            event.preventDefault();
                                            setDeleteOpen(true);
                                        }}
                                        data-test="delete-loan-button"
                                    >
                                        <Trash2 /> Delete
                                    </DropdownMenuItem>
                                </>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                )}
            </div>

            {can.restructure && (
                <RestructureLoanDialog
                    loan={loan}
                    outstanding={outstanding}
                    open={restructureOpen}
                    onOpenChange={setRestructureOpen}
                />
            )}

            {can.delete && (
                <ConfirmDeleteDialog
                    form={destroyLoan.form(loan.id)}
                    title={`Delete loan #${loan.id}?`}
                    description={`This permanently deletes the loan for ${loan.borrower_name}, including its schedule, payments and audit trail. This cannot be undone.`}
                    open={deleteOpen}
                    onOpenChange={setDeleteOpen}
                />
            )}
        </>
    );
}
