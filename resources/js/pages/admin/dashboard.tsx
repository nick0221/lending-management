import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowRight,
    Banknote,
    CalendarClock,
    FileBarChart,
    Landmark,
    Users,
    Wallet,
} from 'lucide-react';
import Heading from '@/components/heading';
import StatCard from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import { formatDate, formatMoney, formatPercent } from '@/lib/format';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as adminLoans, show as showLoan } from '@/routes/admin/loans';
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
    return (
        <>
            <Head title="Administration" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        title="Administration"
                        description="Portfolio health, collections and arrears at a glance."
                    />

                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline">
                            <Link href={adminLoans()} prefetch>
                                <Wallet /> Loans
                            </Link>
                        </Button>
                        <Button asChild variant="outline">
                            <Link href={adminUsers()} prefetch>
                                <Users /> Users
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link href={reportsIndex()} prefetch>
                                <FileBarChart /> Reports
                                <ArrowRight />
                            </Link>
                        </Button>
                    </div>
                </div>

                <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        title="Outstanding"
                        value={formatMoney(summary.outstanding)}
                        hint={`${summary.open_loans} open loan${
                            summary.open_loans === 1 ? '' : 's'
                        }`}
                        icon={Landmark}
                    />
                    <StatCard
                        title="Principal disbursed"
                        value={formatMoney(summary.disbursed)}
                        hint={`${summary.loans} loans on file`}
                        icon={Banknote}
                    />
                    <StatCard
                        title="Collected"
                        value={formatMoney(summary.collected)}
                        hint={`${formatMoney(summary.collected_interest)} interest`}
                        icon={Wallet}
                        tone="success"
                    />
                    <StatCard
                        title="Arrears"
                        value={formatMoney(summary.arrears)}
                        hint={`${summary.defaulted_loans} defaulted`}
                        icon={AlertTriangle}
                        tone={summary.arrears > 0 ? 'danger' : 'default'}
                    />
                </section>

                <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        title="Due today"
                        value={formatMoney(collections.due_today)}
                        icon={CalendarClock}
                    />
                    <StatCard
                        title="Due this week"
                        value={formatMoney(collections.due_this_week)}
                        icon={CalendarClock}
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
                    />
                    <StatCard
                        title="Portfolio at risk"
                        value={formatPercent(risk.par.ratio)}
                        hint={`${formatMoney(risk.par.amount)} · PAR30 ${formatPercent(
                            risk.par30.ratio,
                        )}`}
                        tone={risk.par.ratio > 0 ? 'warning' : 'default'}
                    />
                </section>

                <section className="grid gap-6 lg:grid-cols-3">
                    <div className="space-y-3 lg:col-span-1">
                        <div className="flex items-center justify-between">
                            <h2 className="text-sm font-semibold">Aging</h2>
                            <Link
                                href={reportsIndex()}
                                className="text-xs text-muted-foreground hover:underline"
                                prefetch
                            >
                                View report
                            </Link>
                        </div>

                        <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                            <table className="w-full text-left text-sm">
                                <tbody>
                                    {agingRows.map((row) => (
                                        <tr
                                            key={row.key}
                                            className="border-b border-sidebar-border/70 last:border-b-0 dark:border-sidebar-border"
                                        >
                                            <td className="px-4 py-3 text-muted-foreground">
                                                {row.label}
                                            </td>
                                            <td className="px-4 py-3 text-right font-medium tabular-nums">
                                                {formatMoney(aging[row.key])}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div className="space-y-3 lg:col-span-2">
                        <h2 className="text-sm font-semibold">
                            Recent payments
                        </h2>

                        <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-muted/50 text-xs text-muted-foreground uppercase">
                                        <tr>
                                            <th className="px-4 py-3 font-medium">
                                                Borrower
                                            </th>
                                            <th className="px-4 py-3 font-medium">
                                                Method
                                            </th>
                                            <th className="px-4 py-3 font-medium">
                                                Date
                                            </th>
                                            <th className="px-4 py-3 text-right font-medium">
                                                Amount
                                            </th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        {recentPayments.map((payment) => (
                                            <tr
                                                key={payment.id}
                                                className="border-t border-sidebar-border/70 dark:border-sidebar-border"
                                            >
                                                <td className="px-4 py-3">
                                                    <Link
                                                        href={showLoan(
                                                            payment.loan_id,
                                                        )}
                                                        className="font-medium hover:underline"
                                                    >
                                                        {payment.borrower ??
                                                            `Loan #${payment.loan_id}`}
                                                    </Link>
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    {payment.method_label}
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    {formatDate(
                                                        payment.payment_date,
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-right font-medium tabular-nums">
                                                    {formatMoney(payment.amount)}
                                                </td>
                                            </tr>
                                        ))}

                                        {recentPayments.length === 0 && (
                                            <tr>
                                                <td
                                                    colSpan={4}
                                                    className="px-4 py-8 text-center text-muted-foreground"
                                                >
                                                    No payments recorded yet.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </section>

                <section className="space-y-3">
                    <h2 className="text-sm font-semibold">Accounts</h2>

                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <StatCard title="Total users" value={stats.total} />
                        <StatCard
                            title="Super admins"
                            value={stats.superAdmins}
                        />
                        <StatCard title="Admins" value={stats.admins} />
                        <StatCard title="Users" value={stats.users} />
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
