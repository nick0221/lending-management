import { Head, Link, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    CalendarClock,
    Landmark,
    ShieldCheck,
    Wallet,
} from 'lucide-react';
import { KycBadge } from '@/components/kyc-badge';
import { LoanStatusBadge } from '@/components/loan-status-badge';
import { RoleBadge } from '@/components/role-badge';
import StatCard from '@/components/stat-card';
import { Button } from '@/components/ui/button';
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

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                    <div className="space-y-1">
                        <div className="flex items-center gap-3">
                            <h1 className="text-xl font-semibold tracking-tight">
                                Welcome, {auth.user.name}
                            </h1>

                            {auth.user.role && (
                                <RoleBadge role={auth.user.role} />
                            )}

                            {summary && (
                                <KycBadge
                                    status={summary.borrower.kyc_status}
                                />
                            )}
                        </div>

                        <p className="text-sm text-muted-foreground">
                            {summary
                                ? 'Your loans, balances and upcoming repayments.'
                                : 'You are signed in with the access level assigned to your account.'}
                        </p>
                    </div>

                    {canAccessAdmin && (
                        <Button asChild>
                            <Link href={adminDashboard()} prefetch>
                                <ShieldCheck />
                                Administration
                            </Link>
                        </Button>
                    )}
                </div>

                {summary === null ? (
                    <div className="rounded-xl border border-dashed border-sidebar-border/70 p-8 text-center dark:border-sidebar-border">
                        <p className="font-medium">
                            No borrower profile is linked to this account.
                        </p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Contact an administrator to have your lending
                            profile connected.
                        </p>
                    </div>
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
                            <div className="space-y-3">
                                <h2 className="text-sm font-semibold">
                                    Upcoming repayments
                                </h2>

                                <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                                    <table className="w-full text-left text-sm">
                                        <thead className="bg-muted/50 text-xs text-muted-foreground uppercase">
                                            <tr>
                                                <th className="px-4 py-3 font-medium">
                                                    Due
                                                </th>
                                                <th className="px-4 py-3 font-medium">
                                                    Loan
                                                </th>
                                                <th className="px-4 py-3 text-right font-medium">
                                                    Amount
                                                </th>
                                            </tr>
                                        </thead>

                                        <tbody>
                                            {summary.upcoming.map((row) => (
                                                <tr
                                                    key={`${row.loan_id}-${row.sequence}`}
                                                    className="border-t border-sidebar-border/70 dark:border-sidebar-border"
                                                >
                                                    <td className="px-4 py-3">
                                                        {formatDateOnly(
                                                            row.due_date,
                                                        )}
                                                        {row.days_past_due >
                                                            0 && (
                                                            <span className="ml-2 text-xs text-destructive">
                                                                {
                                                                    row.days_past_due
                                                                }
                                                                d late
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3 text-muted-foreground">
                                                        #{row.loan_id} · instalment{' '}
                                                        {row.sequence}
                                                    </td>
                                                    <td className="px-4 py-3 text-right font-medium tabular-nums">
                                                        {formatMoney(row.amount)}
                                                    </td>
                                                </tr>
                                            ))}

                                            {summary.upcoming.length === 0 && (
                                                <tr>
                                                    <td
                                                        colSpan={3}
                                                        className="px-4 py-8 text-center text-muted-foreground"
                                                    >
                                                        Nothing scheduled.
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div className="space-y-3">
                                <h2 className="text-sm font-semibold">
                                    Recent payments
                                </h2>

                                <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                                    <table className="w-full text-left text-sm">
                                        <thead className="bg-muted/50 text-xs text-muted-foreground uppercase">
                                            <tr>
                                                <th className="px-4 py-3 font-medium">
                                                    Date
                                                </th>
                                                <th className="px-4 py-3 font-medium">
                                                    Loan
                                                </th>
                                                <th className="px-4 py-3 text-right font-medium">
                                                    Amount
                                                </th>
                                            </tr>
                                        </thead>

                                        <tbody>
                                            {summary.payments.map((payment) => (
                                                <tr
                                                    key={payment.id}
                                                    className="border-t border-sidebar-border/70 dark:border-sidebar-border"
                                                >
                                                    <td className="px-4 py-3 text-muted-foreground">
                                                        {formatDate(
                                                            payment.payment_date,
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        #{payment.loan_id}
                                                    </td>
                                                    <td className="px-4 py-3 text-right font-medium tabular-nums">
                                                        {formatMoney(
                                                            payment.amount,
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}

                                            {summary.payments.length === 0 && (
                                                <tr>
                                                    <td
                                                        colSpan={3}
                                                        className="px-4 py-8 text-center text-muted-foreground"
                                                    >
                                                        No payments yet.
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </section>

                        <section className="space-y-3">
                            <h2 className="text-sm font-semibold">My loans</h2>

                            <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-sm">
                                        <thead className="bg-muted/50 text-xs text-muted-foreground uppercase">
                                            <tr>
                                                <th className="px-4 py-3 font-medium">
                                                    Loan
                                                </th>
                                                <th className="px-4 py-3 font-medium">
                                                    Product
                                                </th>
                                                <th className="px-4 py-3 font-medium">
                                                    Status
                                                </th>
                                                <th className="px-4 py-3 text-right font-medium">
                                                    Principal
                                                </th>
                                                <th className="px-4 py-3 text-right font-medium">
                                                    Paid
                                                </th>
                                                <th className="px-4 py-3 text-right font-medium">
                                                    Outstanding
                                                </th>
                                                <th className="px-4 py-3 text-right font-medium">
                                                    Actions
                                                </th>
                                            </tr>
                                        </thead>

                                        <tbody>
                                            {summary.loans.map((loan) => (
                                                <tr
                                                    key={loan.id}
                                                    className="border-t border-sidebar-border/70 dark:border-sidebar-border"
                                                >
                                                    <td className="px-4 py-3 font-medium">
                                                        #{loan.id}
                                                    </td>
                                                    <td className="px-4 py-3 text-muted-foreground">
                                                        {loan.product ?? '—'}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <LoanStatusBadge
                                                            status={loan.status}
                                                        />
                                                    </td>
                                                    <td className="px-4 py-3 text-right tabular-nums">
                                                        {formatMoney(
                                                            loan.principal,
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3 text-right tabular-nums text-muted-foreground">
                                                        {formatMoney(loan.paid)}
                                                    </td>
                                                    <td className="px-4 py-3 text-right font-medium tabular-nums">
                                                        {formatMoney(
                                                            loan.outstanding,
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3 text-right">
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
                                                    </td>
                                                </tr>
                                            ))}

                                            {summary.loans.length === 0 && (
                                                <tr>
                                                    <td
                                                        colSpan={7}
                                                        className="px-4 py-8 text-center text-muted-foreground"
                                                    >
                                                        No loans yet.{' '}
                                                        {canAccessAdmin && (
                                                            <Link
                                                                href={adminLoans()}
                                                                className="underline"
                                                            >
                                                                Manage loans
                                                            </Link>
                                                        )}
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </section>
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
