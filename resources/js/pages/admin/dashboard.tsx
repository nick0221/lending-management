import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowRight,
    Banknote,
    CalendarClock,
    FileBarChart,
    Landmark,
    Plus,
    UserPlus,
    Wallet,
} from 'lucide-react';
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
    formatMoney,
    formatPercent,
} from '@/lib/format';
import { dashboard as adminDashboard } from '@/routes/admin';
import { create as createBorrower } from '@/routes/admin/borrowers';
import {
    create as createLoan,
    index as adminLoans,
    show as showLoan,
} from '@/routes/admin/loans';
import { index as reportsIndex } from '@/routes/admin/reports';
import { index as adminUsers } from '@/routes/admin/users';
import type {
    AgingBuckets,
    Collections,
    PortfolioRisk,
    PortfolioSummary,
    RecentPayment,
} from '@/types';

type Stats = {
    total: number;
    superAdmins: number;
    admins: number;
    users: number;
};

const agingRows: { key: keyof AgingBuckets; label: string }[] = [
    { key: 'current', label: 'Current' },
    { key: 'days_1_30', label: '1 – 30 days' },
    { key: 'days_31_60', label: '31 – 60 days' },
    { key: 'days_61_90', label: '61 – 90 days' },
    { key: 'days_over_90', label: 'Over 90 days' },
];

function dueStatus(asOf: string, dueDate: string) {
    const days = Math.round(
        (Date.parse(`${dueDate}T00:00:00`) - Date.parse(`${asOf}T00:00:00`)) /
            86_400_000,
    );

    if (days < 0) {
        return {
            label: `${Math.abs(days)}d overdue`,
            className: 'border-destructive/30 text-destructive',
        };
    }

    if (days === 0) {
        return {
            label: 'Due today',
            className: 'border-amber-600/30 text-amber-700 dark:text-amber-400',
        };
    }

    return {
        label: `Due in ${days}d`,
        className: 'text-muted-foreground',
    };
}

export default function AdminDashboard({
    stats,
    summary,
    collections,
    aging,
    risk,
    recentPayments,
}: {
    stats: Stats;
    summary: PortfolioSummary;
    collections: Collections;
    aging: AgingBuckets;
    risk: PortfolioRisk;
    recentPayments: RecentPayment[];
}) {
    const portfolioReport = reportsIndex({ query: { tab: 'portfolio' } });
    const collectionsReport = reportsIndex({ query: { tab: 'collections' } });
    const agingReport = reportsIndex({ query: { tab: 'aging' } });
    const overdueLoans = adminLoans({ query: { overdue: '1' } });

    const attentionRows = [
        ...collections.overdue_installments,
        ...collections.upcoming,
    ].slice(0, 8);

    const dueTodayCount = collections.upcoming.filter(
        (installment) => installment.due_date === collections.as_of,
    ).length;

    return (
        <>
            <Head title="Administration" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Administration"
                    description="Portfolio health, collections and arrears at a glance."
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <Link href={createBorrower()} prefetch>
                                    <UserPlus /> Add borrower
                                </Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={createLoan()} prefetch>
                                    <Plus /> New loan
                                </Link>
                            </Button>
                            <Button asChild>
                                <Link href={reportsIndex()} prefetch>
                                    <FileBarChart /> Reports
                                    <ArrowRight />
                                </Link>
                            </Button>
                        </>
                    }
                />

                <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        title="Outstanding"
                        value={formatMoney(summary.outstanding)}
                        hint={`${summary.open_loans} open loan${
                            summary.open_loans === 1 ? '' : 's'
                        }`}
                        icon={Landmark}
                        href={portfolioReport}
                    />
                    <StatCard
                        title="Principal disbursed"
                        value={formatMoney(summary.disbursed)}
                        hint={`${summary.loans} loans on file`}
                        icon={Banknote}
                        href={portfolioReport}
                    />
                    <StatCard
                        title="Collected"
                        value={formatMoney(summary.collected)}
                        hint={`${formatMoney(summary.collected_interest)} interest`}
                        icon={Wallet}
                        tone="success"
                        href={portfolioReport}
                    />
                    <StatCard
                        title="Arrears"
                        value={formatMoney(summary.arrears)}
                        hint={`${summary.defaulted_loans} defaulted`}
                        icon={AlertTriangle}
                        tone={summary.arrears > 0 ? 'danger' : 'default'}
                        href={overdueLoans}
                    />
                </section>

                <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        title="Due today"
                        value={formatMoney(collections.due_today)}
                        hint={`${dueTodayCount} installment${
                            dueTodayCount === 1 ? '' : 's'
                        }`}
                        icon={CalendarClock}
                        href={collectionsReport}
                    />
                    <StatCard
                        title="Due this week"
                        value={formatMoney(collections.due_this_week)}
                        hint={`${collections.upcoming.length} installment${
                            collections.upcoming.length === 1 ? '' : 's'
                        }`}
                        icon={CalendarClock}
                        href={collectionsReport}
                    />
                    <StatCard
                        title="Overdue"
                        value={formatMoney(collections.overdue)}
                        hint={`${collections.overdue_installments.length} installment${
                            collections.overdue_installments.length === 1
                                ? ''
                                : 's'
                        }`}
                        tone={collections.overdue > 0 ? 'danger' : 'default'}
                        href={overdueLoans}
                    />
                    <StatCard
                        title="Portfolio at risk"
                        value={formatPercent(risk.par.ratio)}
                        hint={`${formatMoney(risk.par.amount)} · PAR30 ${formatPercent(
                            risk.par30.ratio,
                        )}`}
                        tone={risk.par.ratio > 0 ? 'warning' : 'default'}
                        href={agingReport}
                    />
                </section>

                <SectionCard
                    title="Needs attention"
                    description="Overdue installments first, then what is due within the next week."
                    actions={
                        <Button size="sm" variant="outline" asChild>
                            <Link href={overdueLoans} prefetch>
                                View overdue loans <ArrowRight />
                            </Link>
                        </Button>
                    }
                >
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Borrower</TableHead>
                                <TableHead className="hidden sm:table-cell">
                                    Due
                                </TableHead>
                                <TableHead className="hidden sm:table-cell">
                                    Status
                                </TableHead>
                                <TableHead className="text-right">
                                    Amount
                                </TableHead>
                            </TableRow>
                        </TableHeader>

                        <TableBody>
                            {attentionRows.map((installment) => {
                                const status = dueStatus(
                                    collections.as_of,
                                    installment.due_date,
                                );

                                return (
                                    <TableRow
                                        key={`${installment.loan_id}-${installment.sequence}`}
                                    >
                                        <TableCell>
                                            <Link
                                                href={showLoan(
                                                    installment.loan_id,
                                                )}
                                                prefetch
                                                className="font-medium hover:underline"
                                            >
                                                {installment.borrower ??
                                                    `Loan #${installment.loan_id}`}
                                            </Link>
                                            <p className="text-xs text-muted-foreground">
                                                Installment{' '}
                                                {installment.sequence}
                                            </p>
                                            <div className="mt-1 flex flex-wrap items-center gap-2 sm:hidden">
                                                <Badge
                                                    variant="outline"
                                                    className={status.className}
                                                >
                                                    {status.label}
                                                </Badge>
                                                <span className="text-xs text-muted-foreground">
                                                    {formatDateOnly(
                                                        installment.due_date,
                                                    )}
                                                </span>
                                            </div>
                                        </TableCell>
                                        <TableCell className="hidden whitespace-nowrap text-muted-foreground sm:table-cell">
                                            {formatDateOnly(
                                                installment.due_date,
                                            )}
                                        </TableCell>
                                        <TableCell className="hidden sm:table-cell">
                                            <Badge
                                                variant="outline"
                                                className={status.className}
                                            >
                                                {status.label}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-right font-medium tabular-nums">
                                            {formatMoney(installment.amount)}
                                        </TableCell>
                                    </TableRow>
                                );
                            })}

                            {attentionRows.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={4}
                                        className="py-8 text-center text-muted-foreground"
                                    >
                                        Nothing is overdue or due in the next
                                        seven days.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </SectionCard>

                <section className="grid gap-6 lg:grid-cols-3">
                    <SectionCard
                        title="Aging"
                        description="Outstanding by days past due."
                        className="lg:col-span-1"
                        actions={
                            <Link
                                href={agingReport}
                                className="text-xs text-muted-foreground hover:underline"
                                prefetch
                            >
                                View report
                            </Link>
                        }
                    >
                        <Table>
                            <TableBody>
                                {agingRows.map((row) => (
                                    <TableRow key={row.key}>
                                        <TableCell className="text-muted-foreground">
                                            {row.label}
                                        </TableCell>
                                        <TableCell className="text-right font-medium tabular-nums">
                                            {formatMoney(aging[row.key])}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </SectionCard>

                    <SectionCard
                        title="Recent payments"
                        description="The latest payments recorded across the portfolio."
                        className="lg:col-span-2"
                    >
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Borrower</TableHead>
                                    <TableHead className="hidden sm:table-cell">
                                        Method
                                    </TableHead>
                                    <TableHead className="hidden sm:table-cell">
                                        Date
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Amount
                                    </TableHead>
                                </TableRow>
                            </TableHeader>

                            <TableBody>
                                {recentPayments.map((payment) => (
                                    <TableRow key={payment.id}>
                                        <TableCell>
                                            <Link
                                                href={showLoan(payment.loan_id)}
                                                prefetch
                                                className="font-medium hover:underline"
                                            >
                                                {payment.borrower ??
                                                    `Loan #${payment.loan_id}`}
                                            </Link>
                                            <p className="mt-1 text-xs text-muted-foreground sm:hidden">
                                                {payment.method_label} ·{' '}
                                                {formatDate(
                                                    payment.payment_date,
                                                )}
                                            </p>
                                        </TableCell>
                                        <TableCell className="hidden text-muted-foreground sm:table-cell">
                                            {payment.method_label}
                                        </TableCell>
                                        <TableCell className="hidden text-muted-foreground sm:table-cell">
                                            {formatDate(payment.payment_date)}
                                        </TableCell>
                                        <TableCell className="text-right font-medium tabular-nums">
                                            {formatMoney(payment.amount)}
                                        </TableCell>
                                    </TableRow>
                                ))}

                                {recentPayments.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={4}
                                            className="py-8 text-center text-muted-foreground"
                                        >
                                            No payments recorded yet.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </SectionCard>
                </section>

                <section className="space-y-3">
                    <h2 className="text-sm font-semibold">Accounts</h2>

                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <StatCard
                            title="Total users"
                            value={stats.total}
                            href={adminUsers()}
                        />
                        <StatCard
                            title="Super admins"
                            value={stats.superAdmins}
                            href={adminUsers({
                                query: { role: 'super_admin' },
                            })}
                        />
                        <StatCard
                            title="Admins"
                            value={stats.admins}
                            href={adminUsers({ query: { role: 'admin' } })}
                        />
                        <StatCard
                            title="Users"
                            value={stats.users}
                            href={adminUsers({ query: { role: 'user' } })}
                        />
                    </div>
                </section>
            </div>
        </>
    );
}

AdminDashboard.layout = {
    breadcrumbs: [
        {
            title: 'Administration',
            href: adminDashboard(),
        },
    ],
};
