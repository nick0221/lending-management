import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    CalendarClock,
    Landmark,
    Printer,
    Wallet,
} from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { LoanStatusBadge } from '@/components/loan-status-badge';
import { PageHeader } from '@/components/page-header';
import { SectionCard } from '@/components/section-card';
import StatCard from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate, formatDateOnly, formatMoney } from '@/lib/format';
import { dashboard } from '@/routes';
import { statement as loanStatement } from '@/routes/loans';
import type {
    BorrowerLoanTotals,
    Loan,
    LoanInstallment,
    Payment,
} from '@/types';

function DetailField({
    label,
    value,
}: {
    label: string;
    value: React.ReactNode;
}) {
    return (
        <div className="space-y-1">
            <dt className="text-xs text-muted-foreground uppercase">{label}</dt>
            <dd className="text-sm font-medium">{value}</dd>
        </div>
    );
}

/** The status chip for one installment in the repayment schedule. */
function InstallmentStatus({ installment }: { installment: LoanInstallment }) {
    if (Number(installment.outstanding) <= 0) {
        return <Badge variant="secondary">Paid</Badge>;
    }

    if (installment.overdue) {
        return (
            <Badge
                variant="outline"
                className="border-destructive/30 text-destructive"
            >
                Overdue · {installment.days_past_due}d
            </Badge>
        );
    }

    return <Badge variant="outline">Due</Badge>;
}

export default function ShowMyLoan({
    loan,
    summary,
    installments,
    payments,
}: {
    loan: Loan;
    summary: BorrowerLoanTotals;
    installments: LoanInstallment[];
    payments: Payment[];
}) {
    return (
        <>
            <Head title={`Loan #${loan.id}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title={`Loan #${loan.id}`}
                    description={`${loan.loan_product_name} · ${loan.interest_method_label} interest`}
                    actions={
                        <>
                            <LoanStatusBadge status={loan.status} />

                            <Button variant="outline" asChild>
                                <a
                                    href={loanStatement.url(loan.id)}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <Printer /> Statement
                                </a>
                            </Button>

                            <Button variant="outline" asChild>
                                <Link href={dashboard()} prefetch>
                                    <ArrowLeft /> Dashboard
                                </Link>
                            </Button>
                        </>
                    }
                />

                <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        title="Outstanding"
                        value={formatMoney(summary.outstanding)}
                        hint={`of ${formatMoney(summary.payable)} payable`}
                        icon={Landmark}
                    />
                    <StatCard
                        title="Paid"
                        value={formatMoney(summary.paid)}
                        hint="Across the schedule"
                        icon={Wallet}
                        tone="success"
                    />
                    <StatCard
                        title="Arrears"
                        value={formatMoney(summary.arrears)}
                        hint={
                            Number(summary.arrears) > 0
                                ? 'Payment past due'
                                : 'All up to date'
                        }
                        icon={AlertTriangle}
                        tone={
                            Number(summary.arrears) > 0 ? 'danger' : 'success'
                        }
                    />
                    <StatCard
                        title="Next due"
                        value={
                            summary.next_due
                                ? formatMoney(summary.next_due.amount)
                                : '—'
                        }
                        hint={
                            summary.next_due
                                ? `${formatDateOnly(
                                      summary.next_due.due_date,
                                  )} · installment #${summary.next_due.sequence}`
                                : 'Nothing outstanding'
                        }
                        icon={CalendarClock}
                        tone={summary.next_due?.overdue ? 'danger' : 'default'}
                    />
                </section>

                <SectionCard
                    title="Loan terms"
                    description="What you agreed to repay and when."
                    contentClassName="p-6"
                >
                    <dl className="grid gap-x-8 gap-y-5 sm:grid-cols-2 lg:grid-cols-4">
                        <DetailField
                            label="Principal"
                            value={formatMoney(loan.principal)}
                        />
                        <DetailField
                            label="Interest"
                            value={`${loan.interest_rate}% · ${loan.interest_method_label}`}
                        />
                        <DetailField
                            label="Repayment"
                            value={`${loan.term_months} months · ${loan.repayment_frequency_label}`}
                        />
                        <DetailField
                            label="Processing fee"
                            value={`${loan.processing_fee_rate}%`}
                        />
                        <DetailField
                            label="Penalty rate"
                            value={`${loan.penalty_rate}%`}
                        />
                        <DetailField
                            label="Grace period"
                            value={`${loan.grace_period_days} days`}
                        />
                        <DetailField
                            label="Disbursed"
                            value={
                                loan.disbursed_at
                                    ? formatDate(loan.disbursed_at)
                                    : 'Not yet disbursed'
                            }
                        />
                        <DetailField
                            label="Closed"
                            value={
                                loan.closed_at
                                    ? formatDate(loan.closed_at)
                                    : '—'
                            }
                        />
                    </dl>

                    {loan.notes && (
                        <p className="mt-6 border-t pt-4 text-sm whitespace-pre-line text-muted-foreground">
                            {loan.notes}
                        </p>
                    )}
                </SectionCard>

                <SectionCard
                    title="Repayment schedule"
                    description="Every installment, oldest first. Scroll sideways for the full breakdown."
                >
                    {installments.length === 0 ? (
                        <EmptyState
                            icon={CalendarClock}
                            title="No schedule yet"
                            description="The repayment schedule appears once the loan is prepared."
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>#</TableHead>
                                        <TableHead>Due</TableHead>
                                        <TableHead className="hidden text-right lg:table-cell">
                                            Principal
                                        </TableHead>
                                        <TableHead className="hidden text-right lg:table-cell">
                                            Interest
                                        </TableHead>
                                        <TableHead className="hidden text-right xl:table-cell">
                                            Fee
                                        </TableHead>
                                        <TableHead className="hidden text-right xl:table-cell">
                                            Penalty
                                        </TableHead>
                                        <TableHead className="hidden text-right md:table-cell">
                                            Paid
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Outstanding
                                        </TableHead>
                                        <TableHead>Status</TableHead>
                                    </TableRow>
                                </TableHeader>

                                <TableBody>
                                    {installments.map((installment) => (
                                        <TableRow key={installment.sequence}>
                                            <TableCell className="font-medium">
                                                {installment.sequence}
                                            </TableCell>
                                            <TableCell className="whitespace-nowrap">
                                                {formatDateOnly(
                                                    installment.due_date,
                                                )}
                                            </TableCell>
                                            <TableCell className="hidden text-right tabular-nums lg:table-cell">
                                                {formatMoney(
                                                    installment.principal_due,
                                                )}
                                            </TableCell>
                                            <TableCell className="hidden text-right tabular-nums lg:table-cell">
                                                {formatMoney(
                                                    installment.interest_due,
                                                )}
                                            </TableCell>
                                            <TableCell className="hidden text-right tabular-nums xl:table-cell">
                                                {formatMoney(
                                                    installment.fee_due,
                                                )}
                                            </TableCell>
                                            <TableCell className="hidden text-right tabular-nums xl:table-cell">
                                                {formatMoney(
                                                    installment.penalty_accrued,
                                                )}
                                            </TableCell>
                                            <TableCell className="hidden text-right text-muted-foreground tabular-nums md:table-cell">
                                                {formatMoney(installment.paid)}
                                            </TableCell>
                                            <TableCell className="text-right font-medium tabular-nums">
                                                {formatMoney(
                                                    installment.outstanding,
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <InstallmentStatus
                                                    installment={installment}
                                                />
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    )}
                </SectionCard>

                <SectionCard
                    title="Payments"
                    description="Every repayment recorded against this loan."
                >
                    {payments.length === 0 ? (
                        <EmptyState
                            icon={Wallet}
                            title="No payments yet"
                            description="Payments appear here once a repayment is recorded."
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Date</TableHead>
                                        <TableHead className="text-right">
                                            Amount
                                        </TableHead>
                                        <TableHead className="hidden md:table-cell">
                                            Method
                                        </TableHead>
                                        <TableHead className="hidden lg:table-cell">
                                            Reference
                                        </TableHead>
                                        <TableHead className="hidden md:table-cell">
                                            Status
                                        </TableHead>
                                        <TableHead className="hidden xl:table-cell">
                                            Recorded by
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>

                                <TableBody>
                                    {payments.map((payment) => (
                                        <TableRow key={payment.id}>
                                            <TableCell className="whitespace-nowrap">
                                                {formatDate(
                                                    payment.payment_date,
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right font-medium tabular-nums">
                                                {formatMoney(payment.amount)}
                                            </TableCell>
                                            <TableCell className="hidden text-muted-foreground md:table-cell">
                                                {payment.method_label}
                                            </TableCell>
                                            <TableCell className="hidden text-muted-foreground lg:table-cell">
                                                {payment.reference ?? '—'}
                                            </TableCell>
                                            <TableCell className="hidden md:table-cell">
                                                {payment.reversed_at ? (
                                                    <Badge variant="destructive">
                                                        Reversed
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="secondary">
                                                        Posted
                                                    </Badge>
                                                )}
                                            </TableCell>
                                            <TableCell className="hidden text-muted-foreground xl:table-cell">
                                                {payment.recorded_by ?? '—'}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    )}
                </SectionCard>
            </div>
        </>
    );
}

ShowMyLoan.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
