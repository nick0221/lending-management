import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarClock,
    Check,
    Pencil,
    Plus,
    Receipt,
    TriangleAlert,
    Wallet,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { ConfirmDelete } from '@/components/confirm-delete';
import { KycBadge } from '@/components/kyc-badge';
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
import {
    formatDate,
    formatDateOnly,
    formatDateTime,
    formatMoney,
} from '@/lib/format';
import { dashboard as adminDashboard } from '@/routes/admin';
import {
    destroy as destroyBorrower,
    edit as editBorrower,
    index as borrowersIndex,
    show as showBorrower,
} from '@/routes/admin/borrowers';
import { create as createLoan, show as showLoan } from '@/routes/admin/loans';
import type {
    BorrowerActivity,
    BorrowerDetail,
    BorrowerLoanSummary,
    BorrowerPayment,
} from '@/types';

type BorrowerSummary = {
    loan_count: number;
    open_loan_count: number;
    outstanding: number;
    arrears: number;
    next_due: {
        loan_id: number;
        sequence: number;
        due_date: string;
        amount: number;
        days_past_due: number;
    } | null;
    loans: BorrowerLoanSummary[];
    payments: BorrowerPayment[];
};

function DetailField({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="space-y-1">
            <dt className="text-xs text-muted-foreground uppercase">{label}</dt>
            <dd className="text-sm font-medium break-words">{value}</dd>
        </div>
    );
}

export default function ShowBorrower({
    borrower,
    summary,
    activity,
    can,
}: {
    borrower: BorrowerDetail;
    summary: BorrowerSummary;
    activity: BorrowerActivity[];
    can: {
        update: boolean;
        delete: boolean;
        viewActivity: boolean;
    };
}) {
    const loansInArrears = summary.loans.filter(
        (loan) => loan.arrears > 0,
    ).length;

    return (
        <>
            <Head title={borrower.name} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="space-y-1">
                    <Button variant="ghost" size="sm" asChild className="-ml-2">
                        <Link href={borrowersIndex()}>
                            <ArrowLeft /> Back to borrowers
                        </Link>
                    </Button>

                    <PageHeader
                        title={borrower.name}
                        description={`Borrower since ${formatDate(borrower.created_at)}`}
                        actions={
                            <>
                                {can.update && (
                                    <Button variant="outline" asChild>
                                        <Link
                                            href={editBorrower(borrower.id)}
                                            prefetch
                                        >
                                            <Pencil /> Edit
                                        </Link>
                                    </Button>
                                )}

                                {can.delete && (
                                    <ConfirmDelete
                                        form={destroyBorrower.form(borrower.id)}
                                        title={`Delete ${borrower.name}?`}
                                        description="This permanently deletes the borrower and every loan, payment and schedule attached to them. This cannot be undone."
                                        testId="delete-borrower-button"
                                    />
                                )}
                            </>
                        }
                    />
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        title="Open loans"
                        value={summary.open_loan_count}
                        hint={`${summary.loan_count} total`}
                        icon={Wallet}
                    />
                    <StatCard
                        title="Outstanding"
                        value={formatMoney(summary.outstanding)}
                        hint="Across all open loans"
                        icon={Receipt}
                    />
                    <StatCard
                        title="Arrears"
                        value={formatMoney(summary.arrears)}
                        tone={summary.arrears > 0 ? 'danger' : 'default'}
                        hint={
                            loansInArrears > 0
                                ? `${loansInArrears} loan${loansInArrears === 1 ? '' : 's'} overdue`
                                : 'Nothing overdue'
                        }
                        icon={TriangleAlert}
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
                                ? formatDateOnly(summary.next_due.due_date)
                                : 'No upcoming installment'
                        }
                        icon={CalendarClock}
                    />
                </div>

                <div className="grid gap-6 xl:grid-cols-3">
                    <div className="space-y-6 xl:col-span-2">
                        <SectionCard
                            title="Loans"
                            description="Every loan filed for this borrower."
                            actions={
                                <Button size="sm" asChild>
                                    <Link href={createLoan()} prefetch>
                                        <Plus /> New loan
                                    </Link>
                                </Button>
                            }
                        >
                            {summary.loans.length === 0 ? (
                                <p className="p-6 text-sm text-muted-foreground">
                                    No loans yet. Originate the first one to
                                    start the file.
                                </p>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Loan</TableHead>
                                            <TableHead>Product</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead className="text-right">
                                                Principal
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Outstanding
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Arrears
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>

                                    <TableBody>
                                        {summary.loans.map((loan) => (
                                            <TableRow key={loan.id}>
                                                <TableCell>
                                                    <Link
                                                        href={showLoan(loan.id)}
                                                        className="font-medium hover:underline"
                                                        prefetch
                                                    >
                                                        Loan #{loan.id}
                                                    </Link>
                                                    <div className="text-xs text-muted-foreground">
                                                        {loan.disbursed_at
                                                            ? `Disbursed ${formatDateOnly(loan.disbursed_at)}`
                                                            : 'Not disbursed'}
                                                    </div>
                                                </TableCell>
                                                <TableCell className="text-muted-foreground">
                                                    {loan.product ?? '—'}
                                                </TableCell>
                                                <TableCell>
                                                    <LoanStatusBadge
                                                        status={loan.status}
                                                    />
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {formatMoney(
                                                        loan.principal,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {formatMoney(
                                                        loan.outstanding,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {loan.arrears > 0 ? (
                                                        <span className="font-medium text-destructive">
                                                            {formatMoney(
                                                                loan.arrears,
                                                            )}
                                                        </span>
                                                    ) : (
                                                        <span className="text-muted-foreground">
                                                            —
                                                        </span>
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </SectionCard>

                        <SectionCard
                            title="Payment history"
                            description="The latest payments recorded against these loans."
                        >
                            {summary.payments.length === 0 ? (
                                <p className="p-6 text-sm text-muted-foreground">
                                    No payments recorded yet.
                                </p>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Date</TableHead>
                                            <TableHead>Loan</TableHead>
                                            <TableHead className="text-right">
                                                Amount
                                            </TableHead>
                                            <TableHead>Method</TableHead>
                                            <TableHead>Recorded by</TableHead>
                                        </TableRow>
                                    </TableHeader>

                                    <TableBody>
                                        {summary.payments.map((payment) => (
                                            <TableRow key={payment.id}>
                                                <TableCell className="text-muted-foreground">
                                                    {formatDateOnly(
                                                        payment.payment_date,
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <Link
                                                        href={showLoan(
                                                            payment.loan_id,
                                                        )}
                                                        className="font-medium hover:underline"
                                                        prefetch
                                                    >
                                                        Loan #{payment.loan_id}
                                                    </Link>
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {formatMoney(
                                                        payment.amount,
                                                    )}
                                                    {payment.reference && (
                                                        <div className="text-xs text-muted-foreground">
                                                            {payment.reference}
                                                        </div>
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-muted-foreground">
                                                    {payment.method_label}
                                                </TableCell>
                                                <TableCell className="text-muted-foreground">
                                                    {payment.recorded_by ?? '—'}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </SectionCard>
                    </div>

                    <div className="space-y-6">
                        <SectionCard
                            title="Profile"
                            description="Contact and identity details."
                            actions={<KycBadge status={borrower.kyc_status} />}
                            contentClassName="p-6"
                        >
                            <dl className="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
                                <DetailField
                                    label="Phone"
                                    value={borrower.phone}
                                />
                                <DetailField
                                    label="Email"
                                    value={borrower.email ?? '—'}
                                />
                                <DetailField
                                    label="National ID"
                                    value={borrower.national_id ?? '—'}
                                />
                                <DetailField
                                    label="Date of birth"
                                    value={formatDateOnly(
                                        borrower.date_of_birth,
                                    )}
                                />
                                <DetailField
                                    label="Address"
                                    value={borrower.address ?? '—'}
                                />
                                <DetailField
                                    label="Linked account"
                                    value={
                                        borrower.user
                                            ? `${borrower.user.name} (${borrower.user.email})`
                                            : 'Not linked'
                                    }
                                />
                            </dl>

                            {borrower.notes && (
                                <div className="mt-6 space-y-1 border-t pt-4">
                                    <div className="text-xs text-muted-foreground uppercase">
                                        Notes
                                    </div>
                                    <p className="text-sm whitespace-pre-line">
                                        {borrower.notes}
                                    </p>
                                </div>
                            )}
                        </SectionCard>

                        <SectionCard
                            title="Notifications"
                            description="Email preferences for this borrower."
                            contentClassName="p-6"
                        >
                            <div className="space-y-4">
                                <div className="text-sm">
                                    {borrower.email ? (
                                        <span className="text-muted-foreground">
                                            {borrower.email}
                                        </span>
                                    ) : (
                                        <span className="text-destructive">
                                            No email address on file
                                        </span>
                                    )}
                                </div>

                                <div className="divide-y">
                                    {[
                                        {
                                            label: 'Due soon',
                                            enabled: borrower.notify_due_soon,
                                        },
                                        {
                                            label: 'Overdue',
                                            enabled: borrower.notify_overdue,
                                        },
                                        {
                                            label: 'Receipts',
                                            enabled: borrower.notify_receipts,
                                        },
                                        {
                                            label: 'Statements',
                                            enabled: borrower.notify_statements,
                                        },
                                    ].map((preference) => (
                                        <div
                                            key={preference.label}
                                            className="flex items-center justify-between py-2 text-sm"
                                        >
                                            <span>{preference.label}</span>

                                            {preference.enabled ? (
                                                <Badge variant="secondary">
                                                    <Check /> On
                                                </Badge>
                                            ) : (
                                                <span className="text-xs text-muted-foreground">
                                                    Off
                                                </span>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </SectionCard>

                        {can.viewActivity && (
                            <SectionCard
                                title="Activity"
                                description="Audit entries for this borrower and their loans."
                            >
                                {activity.length === 0 ? (
                                    <p className="p-6 text-sm text-muted-foreground">
                                        No activity recorded.
                                    </p>
                                ) : (
                                    <ol className="divide-y">
                                        {activity.map((event) => (
                                            <li
                                                key={event.id}
                                                className="space-y-1 px-6 py-3"
                                            >
                                                <div className="flex flex-wrap items-center justify-between gap-2">
                                                    <Badge variant="outline">
                                                        {event.event_label}
                                                    </Badge>
                                                    <span className="text-xs text-muted-foreground">
                                                        {formatDateTime(
                                                            event.created_at,
                                                        )}
                                                    </span>
                                                </div>
                                                <p className="text-sm">
                                                    {event.description}
                                                </p>
                                                {event.user_name && (
                                                    <p className="text-xs text-muted-foreground">
                                                        by {event.user_name}
                                                    </p>
                                                )}
                                            </li>
                                        ))}
                                    </ol>
                                )}
                            </SectionCard>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

ShowBorrower.layout = (props: { borrower: BorrowerDetail }) => ({
    breadcrumbs: [
        {
            title: 'Administration',
            href: adminDashboard(),
        },
        {
            title: 'Borrowers',
            href: borrowersIndex(),
        },
        {
            title: props.borrower.name,
            href: showBorrower(props.borrower.id),
        },
    ],
});
