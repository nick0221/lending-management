import { Head, Link } from '@inertiajs/react';
import { Download } from 'lucide-react';
import Heading from '@/components/heading';
import { LoanStatusBadge } from '@/components/loan-status-badge';
import StatCard from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import { formatDateOnly, formatMoney, formatPercent } from '@/lib/format';
import { dashboard as adminDashboard } from '@/routes/admin';
import { show as showLoan } from '@/routes/admin/loans';
import { download, index as reportsIndex } from '@/routes/admin/reports';
import type {
    AgingBuckets,
    Collections,
    PortfolioRisk,
    PortfolioSummary,
    ReportLoanRow,
} from '@/types';

const agingRows: { key: keyof AgingBuckets; label: string }[] = [
    { key: 'current', label: 'Current' },
    { key: 'days_1_30', label: '1 – 30 days' },
    { key: 'days_31_60', label: '31 – 60 days' },
    { key: 'days_61_90', label: '61 – 90 days' },
    { key: 'days_over_90', label: 'Over 90 days' },
];

export default function ReportsIndex({
    summary,
    aging,
    risk,
    collections,
    loans,
}: {
    summary: PortfolioSummary;
    aging: AgingBuckets;
    risk: PortfolioRisk;
    collections: Collections;
    loans: ReportLoanRow[];
}) {
    const agingTotal = agingRows.reduce(
        (total, row) => total + aging[row.key],
        0,
    );

    const share = (amount: number) =>
        agingTotal > 0 ? (amount / agingTotal) * 100 : 0;

    const collectionRows = [
        ...collections.overdue_installments,
        ...collections.upcoming,
    ];

    return (
        <>
            <Head title="Reports" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        title="Reports"
                        description="Portfolio performance, collections and arrears."
                    />

                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline" size="sm">
                            <a href={download({ report: 'summary' }).url}>
                                <Download /> Summary CSV
                            </a>
                        </Button>
                        <Button asChild variant="outline" size="sm">
                            <a href={download({ report: 'loans' }).url}>
                                <Download /> Loans CSV
                            </a>
                        </Button>
                        <Button asChild variant="outline" size="sm">
                            <a href={download({ report: 'collections' }).url}>
                                <Download /> Collections CSV
                            </a>
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
                    />
                    <StatCard
                        title="Principal disbursed"
                        value={formatMoney(summary.disbursed)}
                        hint={`${summary.loans} loans on file`}
                    />
                    <StatCard
                        title="Collected"
                        value={formatMoney(summary.collected)}
                        hint={formatMoney(summary.collected_interest) + ' interest'}
                        tone="success"
                    />
                    <StatCard
                        title="Arrears"
                        value={formatMoney(summary.arrears)}
                        hint={`${summary.defaulted_loans} defaulted`}
                        tone={summary.arrears > 0 ? 'danger' : 'default'}
                    />
                </section>

                <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        title="Portfolio at risk"
                        value={formatPercent(risk.par.ratio)}
                        hint={formatMoney(risk.par.amount) + ' overdue'}
                        tone={risk.par.ratio > 0 ? 'warning' : 'default'}
                    />
                    <StatCard
                        title="PAR 30"
                        value={formatPercent(risk.par30.ratio)}
                        hint={formatMoney(risk.par30.amount)}
                    />
                    <StatCard
                        title="PAR 60"
                        value={formatPercent(risk.par60.ratio)}
                        hint={formatMoney(risk.par60.amount)}
                    />
                    <StatCard
                        title="PAR 90"
                        value={formatPercent(risk.par90.ratio)}
                        hint={formatMoney(risk.par90.amount)}
                    />
                </section>

                <section className="grid gap-6 lg:grid-cols-2">
                    <div className="space-y-3">
                        <h2 className="text-sm font-semibold">Aging</h2>

                        <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-xs text-muted-foreground uppercase">
                                    <tr>
                                        <th className="px-4 py-3 font-medium">
                                            Bucket
                                        </th>
                                        <th className="px-4 py-3 text-right font-medium">
                                            Outstanding
                                        </th>
                                        <th className="px-4 py-3 text-right font-medium">
                                            Share
                                        </th>
                                    </tr>
                                </thead>

                                <tbody>
                                    {agingRows.map((row) => (
                                        <tr
                                            key={row.key}
                                            className="border-t border-sidebar-border/70 dark:border-sidebar-border"
                                        >
                                            <td className="px-4 py-3 font-medium">
                                                {row.label}
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums">
                                                {formatMoney(aging[row.key])}
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums text-muted-foreground">
                                                {formatPercent(share(aging[row.key]))}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>

                                <tfoot>
                                    <tr className="border-t border-sidebar-border/70 bg-muted/30 dark:border-sidebar-border">
                                        <td className="px-4 py-3 font-medium">
                                            Total
                                        </td>
                                        <td className="px-4 py-3 text-right font-medium tabular-nums">
                                            {formatMoney(agingTotal)}
                                        </td>
                                        <td className="px-4 py-3 text-right tabular-nums text-muted-foreground">
                                            {agingTotal > 0 ? '100.00%' : '0.00%'}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <div className="space-y-3">
                        <h2 className="text-sm font-semibold">Collections</h2>

                        <div className="grid gap-4 sm:grid-cols-3">
                            <StatCard
                                title="Due today"
                                value={formatMoney(collections.due_today)}
                            />
                            <StatCard
                                title="Due this week"
                                value={formatMoney(collections.due_this_week)}
                            />
                            <StatCard
                                title="Overdue"
                                value={formatMoney(collections.overdue)}
                                tone={
                                    collections.overdue > 0
                                        ? 'danger'
                                        : 'default'
                                }
                            />
                        </div>

                        <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                            <div className="max-h-64 overflow-y-auto">
                                <table className="w-full text-left text-sm">
                                    <thead className="sticky top-0 bg-muted/50 text-xs text-muted-foreground uppercase">
                                        <tr>
                                            <th className="px-4 py-3 font-medium">
                                                Borrower
                                            </th>
                                            <th className="px-4 py-3 font-medium">
                                                Due
                                            </th>
                                            <th className="px-4 py-3 text-right font-medium">
                                                Amount
                                            </th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        {collectionRows.map((row) => (
                                            <tr
                                                key={`${row.loan_id}-${row.sequence}`}
                                                className="border-t border-sidebar-border/70 dark:border-sidebar-border"
                                            >
                                                <td className="px-4 py-3">
                                                    <Link
                                                        href={showLoan(
                                                            row.loan_id,
                                                        )}
                                                        className="font-medium hover:underline"
                                                    >
                                                        {row.borrower ??
                                                            `Loan #${row.loan_id}`}
                                                    </Link>
                                                    <span className="text-xs text-muted-foreground">
                                                        {' '}
                                                        · #{row.sequence}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    {formatDateOnly(row.due_date)}
                                                    {row.days_past_due > 0 && (
                                                        <span className="ml-2 text-xs text-destructive">
                                                            {row.days_past_due}d late
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-right tabular-nums">
                                                    {formatMoney(row.amount)}
                                                </td>
                                            </tr>
                                        ))}

                                        {collectionRows.length === 0 && (
                                            <tr>
                                                <td
                                                    colSpan={3}
                                                    className="px-4 py-8 text-center text-muted-foreground"
                                                >
                                                    Nothing due in the next 7
                                                    days.
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
                    <h2 className="text-sm font-semibold">Loan report</h2>

                    <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <div className="max-h-[32rem] overflow-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="sticky top-0 bg-muted/50 text-xs text-muted-foreground uppercase">
                                    <tr>
                                        <th className="px-4 py-3 font-medium">
                                            Loan
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Borrower
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
                                            Arrears
                                        </th>
                                        <th className="px-4 py-3 text-right font-medium">
                                            DPD
                                        </th>
                                    </tr>
                                </thead>

                                <tbody>
                                    {loans.map((loan) => (
                                        <tr
                                            key={loan.id}
                                            className="border-t border-sidebar-border/70 dark:border-sidebar-border"
                                        >
                                            <td className="px-4 py-3">
                                                <Link
                                                    href={showLoan(loan.id)}
                                                    className="font-medium hover:underline"
                                                >
                                                    #{loan.id}
                                                </Link>
                                            </td>
                                            <td className="px-4 py-3">
                                                {loan.borrower ?? '—'}
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
                                                {formatMoney(loan.principal)}
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums text-muted-foreground">
                                                {formatMoney(loan.paid)}
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums">
                                                <span className="font-medium">
                                                    {formatMoney(loan.outstanding)}
                                                </span>
                                                {loan.outstanding_penalty >
                                                    0 && (
                                                    <span className="block text-xs text-muted-foreground">
                                                        incl.{' '}
                                                        {formatMoney(
                                                            loan.outstanding_penalty,
                                                        )}{' '}
                                                        penalty
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums">
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
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums text-muted-foreground">
                                                {loan.days_past_due > 0
                                                    ? loan.days_past_due
                                                    : '—'}
                                            </td>
                                        </tr>
                                    ))}

                                    {loans.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={9}
                                                className="px-4 py-8 text-center text-muted-foreground"
                                            >
                                                No loans on file.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>
        </>
    );
}

ReportsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Administration',
            href: adminDashboard(),
        },
        {
            title: 'Reports',
            href: reportsIndex(),
        },
    ],
};
