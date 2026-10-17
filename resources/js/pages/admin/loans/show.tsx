import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, Check, Trash2, X } from 'lucide-react';
import type { ReactNode } from 'react';
import { DisburseLoanDialog } from '@/components/admin/disburse-loan-dialog';
import { RecordPaymentDialog } from '@/components/admin/record-payment-dialog';
import Heading from '@/components/heading';
import { LoanStatusBadge } from '@/components/loan-status-badge';
import { Button } from '@/components/ui/button';
import { dashboard as adminDashboard } from '@/routes/admin';
import {
    approve as approveLoan,
    destroy as destroyLoan,
    index as loansIndex,
    reject as rejectLoan,
    show as showLoan,
} from '@/routes/admin/loans';
import type {
    Disbursement,
    DisbursementMethodOption,
    Loan,
    LoanAbilities,
    LoanEvent,
    LoanInstallment,
    Payment,
    PaymentMethodOption,
    PaymentTotals,
} from '@/types';

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString() : '—';
}

function DetailField({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="space-y-1">
            <dt className="text-xs text-muted-foreground uppercase">{label}</dt>
            <dd className="text-sm font-medium">{value}</dd>
        </div>
    );
}

export default function ShowLoan({
    loan,
    installments,
    disbursements,
    payments,
    totals,
    events,
    disbursementMethods,
    paymentMethods,
    can,
}: {
    loan: Loan;
    installments: LoanInstallment[];
    disbursements: Disbursement[];
    payments: Payment[];
    totals: PaymentTotals;
    events: LoanEvent[];
    disbursementMethods: DisbursementMethodOption[];
    paymentMethods: PaymentMethodOption[];
    can: LoanAbilities;
}) {
    const scheduleTotals = installments.reduce(
        (totals, installment) => ({
            principal: totals.principal + Number(installment.principal_due),
            interest: totals.interest + Number(installment.interest_due),
            fee: totals.fee + Number(installment.fee_due),
            paid: totals.paid + Number(installment.paid),
            outstanding:
                totals.outstanding + Number(installment.outstanding),
            total:
                totals.total +
                Number(installment.principal_due) +
                Number(installment.interest_due) +
                Number(installment.fee_due),
        }),
        { principal: 0, interest: 0, fee: 0, paid: 0, outstanding: 0, total: 0 },
    );

    return (
        <>
            <Head title={`Loan #${loan.id}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="space-y-1">
                    <Button
                        variant="ghost"
                        size="sm"
                        asChild
                        className="-ml-2"
                    >
                        <Link href={loansIndex()}>
                            <ArrowLeft /> Back to loans
                        </Link>
                    </Button>

                    <Heading
                        title={`Loan #${loan.id}`}
                        description={`${loan.borrower_name} · ${loan.loan_product_name}`}
                    />
                </div>

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
                            outstanding={totals.outstanding}
                        />
                    )}

                    {can.delete && (
                        <Form {...destroyLoan.form(loan.id)}>
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={processing}
                                    data-test="delete-loan-button"
                                >
                                    <Trash2 /> Delete
                                </Button>
                            )}
                        </Form>
                    )}
                </div>

                <div className="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                    <div className="flex items-center justify-between gap-4">
                        <span className="text-sm text-muted-foreground">
                            Status
                        </span>

                        <LoanStatusBadge status={loan.status} />
                    </div>

                    <dl className="mt-6 grid gap-x-8 gap-y-5 sm:grid-cols-2">
                        <div className="space-y-1">
                            <dt className="text-xs text-muted-foreground uppercase">
                                Borrower
                            </dt>
                            <dd className="text-sm font-medium">
                                {loan.borrower_name}
                            </dd>
                        </div>

                        <div className="space-y-1">
                            <dt className="text-xs text-muted-foreground uppercase">
                                Loan product
                            </dt>
                            <dd className="text-sm font-medium">
                                {loan.loan_product_name}
                            </dd>
                        </div>

                        <div className="space-y-1">
                            <dt className="text-xs text-muted-foreground uppercase">
                                Principal
                            </dt>
                            <dd className="text-sm font-medium">
                                {Number(loan.principal).toFixed(2)}
                            </dd>
                        </div>

                        <div className="space-y-1">
                            <dt className="text-xs text-muted-foreground uppercase">
                                Interest
                            </dt>
                            <dd className="text-sm font-medium">
                                {Number(loan.interest_rate).toFixed(2)}%{' '}
                                {loan.interest_method_label}
                            </dd>
                        </div>

                        <div className="space-y-1">
                            <dt className="text-xs text-muted-foreground uppercase">
                                Repayment
                            </dt>
                            <dd className="text-sm font-medium">
                                {loan.repayment_frequency_label}
                            </dd>
                        </div>

                        <div className="space-y-1">
                            <dt className="text-xs text-muted-foreground uppercase">
                                Term
                            </dt>
                            <dd className="text-sm font-medium">
                                {loan.term_months} months
                            </dd>
                        </div>

                        <div className="space-y-1">
                            <dt className="text-xs text-muted-foreground uppercase">
                                Processing fee
                            </dt>
                            <dd className="text-sm font-medium">
                                {Number(loan.processing_fee_rate).toFixed(2)}%
                            </dd>
                        </div>

                        <div className="space-y-1">
                            <dt className="text-xs text-muted-foreground uppercase">
                                Applied
                            </dt>
                            <dd className="text-sm font-medium">
                                {formatDate(loan.applied_at)}
                            </dd>
                        </div>

                        <div className="space-y-1">
                            <dt className="text-xs text-muted-foreground uppercase">
                                Approved
                            </dt>
                            <dd className="text-sm font-medium">
                                {formatDate(loan.approved_at)}
                            </dd>
                        </div>

                        <div className="space-y-1">
                            <dt className="text-xs text-muted-foreground uppercase">
                                Rejected
                            </dt>
                            <dd className="text-sm font-medium">
                                {formatDate(loan.rejected_at)}
                            </dd>
                        </div>

                        <div className="space-y-1">
                            <dt className="text-xs text-muted-foreground uppercase">
                                Disbursed
                            </dt>
                            <dd className="text-sm font-medium">
                                {formatDate(loan.disbursed_at)}
                            </dd>
                        </div>

                        <div className="space-y-1 sm:col-span-2">
                            <dt className="text-xs text-muted-foreground uppercase">
                                Notes
                            </dt>
                            <dd className="text-sm whitespace-pre-line">
                                {loan.notes || '—'}
                            </dd>
                        </div>
                    </dl>
                </div>

                {installments.length > 0 && (
                    <div className="grid gap-4 rounded-xl border border-sidebar-border/70 p-6 sm:grid-cols-4 dark:border-sidebar-border">
                        <div className="space-y-1">
                            <p className="text-xs text-muted-foreground uppercase">
                                Payable
                            </p>
                            <p className="text-lg font-semibold">
                                {Number(totals.payable).toFixed(2)}
                            </p>
                        </div>

                        <div className="space-y-1">
                            <p className="text-xs text-muted-foreground uppercase">
                                Paid
                            </p>
                            <p className="text-lg font-semibold">
                                {Number(totals.paid).toFixed(2)}
                            </p>
                        </div>

                        <div className="space-y-1">
                            <p className="text-xs text-muted-foreground uppercase">
                                Outstanding
                            </p>
                            <p className="text-lg font-semibold">
                                {Number(totals.outstanding).toFixed(2)}
                            </p>
                        </div>

                        <div className="space-y-1">
                            <p className="text-xs text-muted-foreground uppercase">
                                Arrears
                            </p>
                            <p
                                className={
                                    Number(totals.arrears) > 0
                                        ? 'text-lg font-semibold text-destructive'
                                        : 'text-lg font-semibold'
                                }
                            >
                                {Number(totals.arrears).toFixed(2)}
                            </p>
                        </div>
                    </div>
                )}

                {disbursements.length > 0 && (
                    <section className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <div className="flex flex-wrap items-baseline justify-between gap-2 border-b border-sidebar-border/70 px-6 py-4 dark:border-sidebar-border">
                            <h2 className="text-sm font-medium">Disbursement</h2>

                            <p className="text-xs text-muted-foreground">
                                {disbursements.length} payout
                                {disbursements.length === 1 ? '' : 's'}
                            </p>
                        </div>

                        <div className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                            {disbursements.map((disbursement) => (
                                <dl
                                    key={disbursement.id}
                                    className="grid gap-x-8 gap-y-5 p-6 sm:grid-cols-2"
                                >
                                    <DetailField
                                        label="Amount"
                                        value={Number(
                                            disbursement.amount,
                                        ).toFixed(2)}
                                    />
                                    <DetailField
                                        label="Method"
                                        value={disbursement.method_label}
                                    />
                                    <DetailField
                                        label="Reference"
                                        value={disbursement.reference || '—'}
                                    />
                                    <DetailField
                                        label="Disbursed"
                                        value={formatDate(
                                            disbursement.disbursed_at,
                                        )}
                                    />
                                    <DetailField
                                        label="Recorded by"
                                        value={disbursement.recorded_by || '—'}
                                    />
                                </dl>
                            ))}
                        </div>
                    </section>
                )}

                <section className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <div className="flex flex-wrap items-baseline justify-between gap-2 border-b border-sidebar-border/70 px-6 py-4 dark:border-sidebar-border">
                        <h2 className="text-sm font-medium">Repayments</h2>

                        <p className="text-xs text-muted-foreground">
                            {payments.length} payment
                            {payments.length === 1 ? '' : 's'}
                        </p>
                    </div>

                    {payments.length === 0 ? (
                        <p className="px-6 py-8 text-center text-sm text-muted-foreground">
                            No payments recorded yet.
                        </p>
                    ) : (
                        <div className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                            {payments.map((payment) => (
                                <dl
                                    key={payment.id}
                                    className="grid gap-x-8 gap-y-5 p-6 sm:grid-cols-2"
                                >
                                    <DetailField
                                        label="Amount"
                                        value={Number(payment.amount).toFixed(2)}
                                    />
                                    <DetailField
                                        label="Method"
                                        value={payment.method_label}
                                    />
                                    <DetailField
                                        label="Reference"
                                        value={payment.reference || '—'}
                                    />
                                    <DetailField
                                        label="Received"
                                        value={formatDate(payment.payment_date)}
                                    />
                                    <DetailField
                                        label="Recorded by"
                                        value={payment.recorded_by || '—'}
                                    />
                                    <DetailField
                                        label="Allocated to"
                                        value={
                                            payment.allocations.length === 0
                                                ? 'Unallocated'
                                                : payment.allocations
                                                      .map(
                                                          (allocation) =>
                                                              `#${
                                                                  allocation.installment_sequence ??
                                                                  '—'
                                                              }`,
                                                      )
                                                      .join(', ')
                                        }
                                    />
                                    {payment.notes && (
                                        <div className="space-y-1 sm:col-span-2">
                                            <dt className="text-xs text-muted-foreground uppercase">
                                                Notes
                                            </dt>
                                            <dd className="text-sm whitespace-pre-line">
                                                {payment.notes}
                                            </dd>
                                        </div>
                                    )}
                                </dl>
                            ))}
                        </div>
                    )}
                </section>

                <section className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <div className="flex flex-wrap items-baseline justify-between gap-2 border-b border-sidebar-border/70 px-6 py-4 dark:border-sidebar-border">
                        <h2 className="text-sm font-medium">Lifecycle</h2>

                        <p className="text-xs text-muted-foreground">
                            {events.length} event
                            {events.length === 1 ? '' : 's'}
                        </p>
                    </div>

                    {events.length === 0 ? (
                        <p className="px-6 py-8 text-center text-sm text-muted-foreground">
                            No activity recorded yet.
                        </p>
                    ) : (
                        <ol className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                            {events.map((event) => (
                                <li
                                    key={event.id}
                                    className="flex items-start gap-4 px-6 py-4"
                                >
                                    <span className="mt-1.5 size-2 shrink-0 rounded-full bg-primary" />

                                    <div className="flex-1 space-y-0.5">
                                        <p className="text-sm font-medium">
                                            {event.action_label}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {event.user ?? 'System'} ·{' '}
                                            {formatDate(event.created_at)}
                                        </p>
                                    </div>
                                </li>
                            ))}
                        </ol>
                    )}
                </section>

                <section className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <div className="flex flex-wrap items-baseline justify-between gap-2 border-b border-sidebar-border/70 px-6 py-4 dark:border-sidebar-border">
                        <h2 className="text-sm font-medium">
                            Repayment schedule
                        </h2>

                        <p className="text-xs text-muted-foreground">
                            {installments.length} installment
                            {installments.length === 1 ? '' : 's'}
                        </p>
                    </div>

                    {installments.length === 0 ? (
                        <p className="px-6 py-8 text-center text-sm text-muted-foreground">
                            The schedule is generated when the loan is
                            approved.
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-xs text-muted-foreground uppercase">
                                    <tr>
                                        <th className="px-6 py-3 font-medium">
                                            #
                                        </th>
                                        <th className="px-6 py-3 font-medium">
                                            Due date
                                        </th>
                                        <th className="px-6 py-3 text-right font-medium">
                                            Principal
                                        </th>
                                        <th className="px-6 py-3 text-right font-medium">
                                            Interest
                                        </th>
                                        <th className="px-6 py-3 text-right font-medium">
                                            Fee
                                        </th>
                                        <th className="px-6 py-3 text-right font-medium">
                                            Paid
                                        </th>
                                        <th className="px-6 py-3 text-right font-medium">
                                            Outstanding
                                        </th>
                                    </tr>
                                </thead>

                                <tbody>
                                    {installments.map((installment) => (
                                        <tr
                                            key={installment.sequence}
                                            className="border-t border-sidebar-border/70 dark:border-sidebar-border"
                                        >
                                            <td className="px-6 py-3 text-muted-foreground">
                                                {installment.sequence}
                                            </td>
                                            <td className="px-6 py-3">
                                                {new Date(
                                                    installment.due_date,
                                                ).toLocaleDateString()}
                                            </td>
                                            <td className="px-6 py-3 text-right">
                                                {Number(
                                                    installment.principal_due,
                                                ).toFixed(2)}
                                            </td>
                                            <td className="px-6 py-3 text-right">
                                                {Number(
                                                    installment.interest_due,
                                                ).toFixed(2)}
                                            </td>
                                            <td className="px-6 py-3 text-right">
                                                {Number(
                                                    installment.fee_due,
                                                ).toFixed(2)}
                                            </td>
                                            <td className="px-6 py-3 text-right">
                                                {Number(
                                                    installment.paid,
                                                ).toFixed(2)}
                                            </td>
                                            <td className="px-6 py-3 text-right text-muted-foreground">
                                                {Number(
                                                    installment.outstanding,
                                                ).toFixed(2)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>

                                <tfoot className="border-t border-sidebar-border/70 font-medium dark:border-sidebar-border">
                                    <tr>
                                        <td
                                            className="px-6 py-3"
                                            colSpan={2}
                                        >
                                            Total payable
                                        </td>
                                        <td className="px-6 py-3 text-right">
                                            {scheduleTotals.principal.toFixed(2)}
                                        </td>
                                        <td className="px-6 py-3 text-right">
                                            {scheduleTotals.interest.toFixed(2)}
                                        </td>
                                        <td className="px-6 py-3 text-right">
                                            {scheduleTotals.fee.toFixed(2)}
                                        </td>
                                        <td className="px-6 py-3 text-right">
                                            {scheduleTotals.paid.toFixed(2)}
                                        </td>
                                        <td className="px-6 py-3 text-right">
                                            {scheduleTotals.outstanding.toFixed(2)}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}

ShowLoan.layout = (props: { loan: Loan }) => ({
    breadcrumbs: [
        {
            title: 'Administration',
            href: adminDashboard(),
        },
        {
            title: 'Loans',
            href: loansIndex(),
        },
        {
            title: `Loan #${props.loan.id}`,
            href: showLoan(props.loan.id),
        },
    ],
});
