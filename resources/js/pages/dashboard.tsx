import { Head, Link, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    CalendarClock,
    Info,
    Landmark,
    ShieldCheck,
    Wallet,
} from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { KycBadge } from '@/components/kyc-badge';
import { LoanStatusBadge } from '@/components/loan-status-badge';
import { PageHeader } from '@/components/page-header';
import { RoleBadge } from '@/components/role-badge';
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
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as adminLoans, show as showLoan } from '@/routes/admin/loans';
import type { BorrowerSummary } from '@/types';

export default function Dashboard({
    summary,
}: {
    summary: BorrowerSummary | null;
}) {
    const { auth } = usePage().props;
    const canAccessAdmin =
        auth.user?.role === 'admin' || auth.user?.role === 'super_admin';

    const hiddenUpcoming = summary
        ? summary.upcoming_count - summary.upcoming.length
        : 0;
    const hiddenPayments = summary
        ? summary.payment_count - summary.payments.length
        : 0;

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title={`Welcome, ${auth.user.name}`}
                    description={
                        summary
                            ? 'Your loans, balances and upcoming repayments.'
                            : 'You are signed in with the access level assigned to your account.'
                    }
                    actions={
                        <>
                            {auth.user.role && (
                                <RoleBadge role={auth.user.role} />
                            )}

                            {summary && (
                                <KycBadge
                                    status={summary.borrower.kyc_status}
                                />
                            )}

                            {canAccessAdmin && (
                                <Button asChild>
                                    <Link href={adminDashboard()} prefetch>
                                        <ShieldCheck />
                                        Administration
                                    </Link>
                                </Button>
                            )}
                        </>
                    }
                />

                {summary === null ? (
                    <EmptyState
                        icon={Info}
                        title="No borrower profile is linked to this account."
                        description="Contact an administrator to have your lending profile connected."
                        className="rounded-xl border border-dashed border-sidebar-border/70 dark:border-sidebar-border"
                    />
                ) : (
                    <>
                        <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <StatCard
                                title="Outstanding"
                                value={formatMoney(summary.outstanding)}
                                hint={`${summary.open_loan_count} open loan${
                                    summary.open_loan_count === 1 ? '' : 's'
                                }`}
                                icon={Landmark}
                            />
                            <StatCard
                                title="Arrears"
                                value={formatMoney(summary.arrears)}
                                hint={
                                    summary.arrears > 0
                                        ? 'Payment past due'
                                        : 'All up to date'
                                }
                                icon={AlertTriangle}
                                tone={
                                    summary.arrears > 0 ? 'danger' : 'success'
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
                                          )} · installment #${
                                              summary.next_due.sequence
                                          }`
                                        : 'Nothing scheduled'
                                }
                                icon={CalendarClock}
                            />
                            <StatCard
                                title="Loans"
                                value={summary.loan_count}
                                hint={`${summary.borrower.kyc_status_label} KYC`}
                                icon={Wallet}
                            />
                        </section>

                        <section className="grid gap-6 lg:grid-cols-2">
                            <SectionCard
                                title="Upcoming repayments"
                                description="Installments due across your open loans."
                                actions={
                                    hiddenUpcoming > 0 ? (
                                        <Badge variant="outline">
                                            First {summary.upcoming.length} of{' '}
                                            {summary.upcoming_count}
                                        </Badge>
                                    ) : undefined
                                }
                            >
                                {summary.upcoming.length === 0 ? (
                                    <EmptyState
                                        icon={CalendarClock}
                                        title="Nothing scheduled"
                                        description="You have no outstanding installments."
                                    />
                                ) : (
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Due</TableHead>
                                                <TableHead className="hidden md:table-cell">
                                                    Loan
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    Amount
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>

                                        <TableBody>
                                            {summary.upcoming.map((row) => (
                                                <TableRow
                                                    key={`${row.loan_id}-${row.sequence}`}
                                                >
                                                    <TableCell>
                                                        <div className="flex flex-wrap items-center gap-2">
                                                            <span>
                                                                {formatDateOnly(
                                                                    row.due_date,
                                                                )}
                                                            </span>

                                                            {row.days_past_due >
                                                                0 && (
                                                                <Badge
                                                                    variant="outline"
                                                                    className="border-destructive/30 text-destructive"
                                                                >
                                                                    {
                                                                        row.days_past_due
                                                                    }
                                                                    d late
                                                                </Badge>
                                                            )}
                                                        </div>

                                                        <p className="mt-0.5 text-xs text-muted-foreground md:hidden">
                                                            #{row.loan_id} ·
                                                            installment{' '}
                                                            {row.sequence}
                                                        </p>
                                                    </TableCell>

                                                    <TableCell className="hidden text-muted-foreground md:table-cell">
                                                        #{row.loan_id} ·
                                                        installment{' '}
                                                        {row.sequence}
                                                    </TableCell>

                                                    <TableCell className="text-right font-medium tabular-nums">
                                                        {formatMoney(
                                                            row.amount,
                                                        )}
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                )}
                            </SectionCard>

                            <SectionCard
                                title="Recent payments"
                                description="The latest repayments on your loans."
                                actions={
                                    hiddenPayments > 0 ? (
                                        <Badge variant="outline">
                                            Latest {summary.payments.length} of{' '}
                                            {summary.payment_count}
                                        </Badge>
                                    ) : undefined
                                }
                            >
                                {summary.payments.length === 0 ? (
                                    <EmptyState
                                        icon={Wallet}
                                        title="No payments yet"
                                        description="Payments appear here once a repayment is recorded."
                                    />
                                ) : (
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Date</TableHead>
                                                <TableHead className="hidden md:table-cell">
                                                    Loan
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    Amount
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>

                                        <TableBody>
                                            {summary.payments.map((payment) => (
                                                <TableRow key={payment.id}>
                                                    <TableCell>
                                                        <span className="text-muted-foreground">
                                                            {formatDate(
                                                                payment.payment_date,
                                                            )}
                                                        </span>

                                                        <p className="mt-0.5 text-xs text-muted-foreground md:hidden">
                                                            #{payment.loan_id}
                                                        </p>
                                                    </TableCell>

                                                    <TableCell className="hidden md:table-cell">
                                                        #{payment.loan_id}
                                                    </TableCell>

                                                    <TableCell className="text-right font-medium tabular-nums">
                                                        {formatMoney(
                                                            payment.amount,
                                                        )}
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                )}
                            </SectionCard>
                        </section>

                        <SectionCard
                            title="My loans"
                            description={`${summary.loan_count} loan${
                                summary.loan_count === 1 ? '' : 's'
                            } on file, ${summary.open_loan_count} open.`}
                        >
                            {summary.loans.length === 0 ? (
                                <EmptyState
                                    icon={Wallet}
                                    title="No loans yet"
                                    description="Loans appear here once an administrator files one for you."
                                    action={
                                        canAccessAdmin ? (
                                            <Button asChild size="sm">
                                                <Link href={adminLoans()}>
                                                    Manage loans
                                                </Link>
                                            </Button>
                                        ) : undefined
                                    }
                                />
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Loan</TableHead>
                                            <TableHead className="hidden md:table-cell">
                                                Product
                                            </TableHead>
                                            <TableHead className="hidden md:table-cell">
                                                Status
                                            </TableHead>
                                            <TableHead className="hidden text-right md:table-cell">
                                                Principal
                                            </TableHead>
                                            <TableHead className="hidden text-right lg:table-cell">
                                                Paid
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Outstanding
                                            </TableHead>
                                            <TableHead className="hidden text-right md:table-cell">
                                                Actions
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>

                                    <TableBody>
                                        {summary.loans.map((loan) => (
                                            <TableRow key={loan.id}>
                                                <TableCell className="font-medium">
                                                    {canAccessAdmin ? (
                                                        <Link
                                                            href={showLoan(
                                                                loan.id,
                                                            )}
                                                            prefetch
                                                            className="underline-offset-4 hover:underline"
                                                        >
                                                            #{loan.id}
                                                        </Link>
                                                    ) : (
                                                        `#${loan.id}`
                                                    )}

                                                    <p className="mt-0.5 text-xs text-muted-foreground md:hidden">
                                                        {loan.product ?? '—'} ·{' '}
                                                        {formatMoney(loan.paid)}{' '}
                                                        paid
                                                    </p>

                                                    <div className="mt-1 md:hidden">
                                                        <LoanStatusBadge
                                                            status={loan.status}
                                                        />
                                                    </div>
                                                </TableCell>

                                                <TableCell className="hidden text-muted-foreground md:table-cell">
                                                    {loan.product ?? '—'}
                                                </TableCell>

                                                <TableCell className="hidden md:table-cell">
                                                    <LoanStatusBadge
                                                        status={loan.status}
                                                    />
                                                </TableCell>

                                                <TableCell className="hidden text-right tabular-nums md:table-cell">
                                                    {formatMoney(
                                                        loan.principal,
                                                    )}
                                                </TableCell>

                                                <TableCell className="hidden text-right text-muted-foreground tabular-nums lg:table-cell">
                                                    {formatMoney(loan.paid)}
                                                </TableCell>

                                                <TableCell className="text-right font-medium tabular-nums">
                                                    {formatMoney(
                                                        loan.outstanding,
                                                    )}
                                                </TableCell>

                                                <TableCell className="hidden text-right md:table-cell">
                                                    {canAccessAdmin && (
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            asChild
                                                        >
                                                            <Link
                                                                href={showLoan(
                                                                    loan.id,
                                                                )}
                                                                prefetch
                                                            >
                                                                View
                                                            </Link>
                                                        </Button>
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </SectionCard>
                    </>
                )}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
