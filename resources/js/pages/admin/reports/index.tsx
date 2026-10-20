import { Head, Link } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { FilterTabs } from '@/components/filter-tabs';
import { LoanStatusBadge } from '@/components/loan-status-badge';
import { PageHeader } from '@/components/page-header';
import { SectionCard } from '@/components/section-card';
import StatCard from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
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

type ReportsTab = 'portfolio' | 'aging' | 'collections' | 'arrears';

function LoanReportTable({
    loans,
    emptyMessage,
}: {
    loans: ReportLoanRow[];
    emptyMessage: string;
}) {
    const totals = loans.reduce(
        (totals, loan) => ({
            principal: totals.principal + loan.principal,
            paid: totals.paid + loan.paid,
            outstanding: totals.outstanding + loan.outstanding,
            arrears: totals.arrears + loan.arrears,
        }),
        { principal: 0, paid: 0, outstanding: 0, arrears: 0 },
    );

    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Loan</TableHead>
                    <TableHead>Borrower</TableHead>
                    <TableHead>Product</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead className="text-right">Principal</TableHead>
                    <TableHead className="text-right">Paid</TableHead>
                    <TableHead className="text-right">Outstanding</TableHead>
                    <TableHead className="text-right">Arrears</TableHead>
                    <TableHead className="text-right">DPD</TableHead>
                </TableRow>
            </TableHeader>

            <TableBody>
                {loans.map((loan) => (
                    <TableRow key={loan.id}>
                        <TableCell>
                            <Link
                                href={showLoan(loan.id)}
                                className="font-medium hover:underline"
                                prefetch
                            >
                                #{loan.id}
                            </Link>
                        </TableCell>
                        <TableCell>{loan.borrower ?? '—'}</TableCell>
                        <TableCell className="text-muted-foreground">
                            {loan.product ?? '—'}
                        </TableCell>
                        <TableCell>
                            <LoanStatusBadge status={loan.status} />
                        </TableCell>
                        <TableCell className="text-right tabular-nums">
                            {formatMoney(loan.principal)}
                        </TableCell>
                        <TableCell className="text-right text-muted-foreground tabular-nums">
                            {formatMoney(loan.paid)}
                        </TableCell>
                        <TableCell className="text-right tabular-nums">
                            <span className="font-medium">
                                {formatMoney(loan.outstanding)}
                            </span>
                            {loan.outstanding_penalty > 0 && (
                                <span className="block text-xs text-muted-foreground">
                                    incl.{' '}
                                    {formatMoney(loan.outstanding_penalty)}{' '}
                                    penalty
                                </span>
                            )}
                        </TableCell>
                        <TableCell className="text-right tabular-nums">
                            {loan.arrears > 0 ? (
                                <span className="font-medium text-destructive">
                                    {formatMoney(loan.arrears)}
                                </span>
                            ) : (
                                <span className="text-muted-foreground">—</span>
                            )}
                        </TableCell>
                        <TableCell className="text-right text-muted-foreground tabular-nums">
                            {loan.days_past_due > 0 ? loan.days_past_due : '—'}
                        </TableCell>
                    </TableRow>
                ))}

                {loans.length === 0 && (
                    <TableRow>
                        <TableCell
                            colSpan={9}
                            className="py-8 text-center text-muted-foreground"
                        >
                            {emptyMessage}
                        </TableCell>
                    </TableRow>
                )}
            </TableBody>

            {loans.length > 0 && (
                <TableFooter>
                    <TableRow>
                        <TableCell colSpan={4}>Total</TableCell>
                        <TableCell className="text-right tabular-nums">
                            {formatMoney(totals.principal)}
                        </TableCell>
                        <TableCell className="text-right tabular-nums">
                            {formatMoney(totals.paid)}
                        </TableCell>
                        <TableCell className="text-right tabular-nums">
                            {formatMoney(totals.outstanding)}
                        </TableCell>
                        <TableCell className="text-right tabular-nums">
                            {formatMoney(totals.arrears)}
                        </TableCell>
                        <TableCell />
                    </TableRow>
                </TableFooter>
            )}
        </Table>
    );
}

export default function ReportsIndex({
    summary,
    aging,
    risk,
    collections,
    loans,
    currentTab,
}: {
    summary: PortfolioSummary;
    aging: AgingBuckets;
    risk: PortfolioRisk;
    collections: Collections;
    loans: ReportLoanRow[];
    currentTab: ReportsTab;
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
    ].sort((a, b) => a.due_date.localeCompare(b.due_date));

    const arrearsLoans = loans
        .filter((loan) => loan.arrears > 0)
        .sort((a, b) => b.arrears - a.arrears);

    const totalArrears = arrearsLoans.reduce(
        (total, loan) => total + loan.arrears,
        0,
    );

    const worstDpd = arrearsLoans.reduce(
        (max, loan) => Math.max(max, loan.days_past_due),
        0,
    );

    const exportMenu = (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline" size="sm">
                    <Download /> Export
                </Button>
            </DropdownMenuTrigger>

            <DropdownMenuContent align="end" className="w-48">
                <DropdownMenuItem asChild>
                    <a href={download({ report: 'summary' }).url}>
                        Portfolio summary
                    </a>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <a href={download({ report: 'loans' }).url}>Loan report</a>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <a href={download({ report: 'collections' }).url}>
                        Collections
                    </a>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );

    return (
        <>
            <Head title="Reports" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Reports"
                    description="Portfolio performance, collections and arrears."
                    actions={exportMenu}
                />

                <FilterTabs
                    tabs={[
                        {
                            value: 'portfolio',
                            label: 'Portfolio',
                            count: loans.length,
                            href: reportsIndex(),
                        },
                        {
                            value: 'aging',
                            label: 'Aging',
                            href: reportsIndex({ query: { tab: 'aging' } }),
                        },
                        {
                            value: 'collections',
                            label: 'Collections',
                            count: collectionRows.length,
                            href: reportsIndex({
                                query: { tab: 'collections' },
                            }),
                        },
                        {
                            value: 'arrears',
                            label: 'Arrears',
                            count: arrearsLoans.length,
                            href: reportsIndex({ query: { tab: 'arrears' } }),
                        },
                    ]}
                    value={currentTab}
                    label="Report sections"
                />

                {currentTab === 'portfolio' && (
                    <>
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
                                hint={
                                    formatMoney(summary.collected_interest) +
                                    ' interest'
                                }
                                tone="success"
                            />
                            <StatCard
                                title="Arrears"
                                value={formatMoney(summary.arrears)}
                                hint={`${summary.defaulted_loans} defaulted`}
                                tone={
                                    summary.arrears > 0 ? 'danger' : 'default'
                                }
                            />
                        </section>

                        <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <StatCard
                                title="Portfolio at risk"
                                value={formatPercent(risk.par.ratio)}
                                hint={formatMoney(risk.par.amount) + ' overdue'}
                                tone={
                                    risk.par.ratio > 0 ? 'warning' : 'default'
                                }
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

                        <SectionCard
                            title="Loan report"
                            description={`${loans.length} loan${
                                loans.length === 1 ? '' : 's'
                            } on file.`}
                        >
                            <LoanReportTable
                                loans={loans}
                                emptyMessage="No loans on file."
                            />
                        </SectionCard>
                    </>
                )}

                {currentTab === 'aging' && (
                    <SectionCard
                        title="Aging"
                        description="Outstanding balance by days past due."
                    >
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Bucket</TableHead>
                                    <TableHead className="text-right">
                                        Outstanding
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Share
                                    </TableHead>
                                </TableRow>
                            </TableHeader>

                            <TableBody>
                                {agingRows.map((row) => (
                                    <TableRow key={row.key}>
                                        <TableCell className="font-medium">
                                            {row.label}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatMoney(aging[row.key])}
                                        </TableCell>
                                        <TableCell className="text-right text-muted-foreground tabular-nums">
                                            {formatPercent(
                                                share(aging[row.key]),
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>

                            <TableFooter>
                                <TableRow>
                                    <TableCell>Total</TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {formatMoney(agingTotal)}
                                    </TableCell>
                                    <TableCell className="text-right text-muted-foreground tabular-nums">
                                        {agingTotal > 0 ? '100.00%' : '0.00%'}
                                    </TableCell>
                                </TableRow>
                            </TableFooter>
                        </Table>
                    </SectionCard>
                )}

                {currentTab === 'collections' && (
                    <>
                        <section className="grid gap-4 sm:grid-cols-3">
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
                        </section>

                        <SectionCard
                            title="Due in the next 7 days"
                            description={`As of ${formatDateOnly(
                                collections.as_of,
                            )}. Overdue installments are listed first.`}
                        >
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Borrower</TableHead>
                                        <TableHead>Due</TableHead>
                                        <TableHead className="text-right">
                                            Amount
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>

                                <TableBody>
                                    {collectionRows.map((row) => (
                                        <TableRow
                                            key={`${row.loan_id}-${row.sequence}`}
                                        >
                                            <TableCell>
                                                <Link
                                                    href={showLoan(row.loan_id)}
                                                    className="font-medium hover:underline"
                                                    prefetch
                                                >
                                                    {row.borrower ??
                                                        `Loan #${row.loan_id}`}
                                                </Link>
                                                <span className="text-xs text-muted-foreground">
                                                    {' '}
                                                    · #{row.sequence}
                                                </span>
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {formatDateOnly(row.due_date)}
                                                {row.days_past_due > 0 && (
                                                    <span className="ml-2 text-xs text-destructive">
                                                        {row.days_past_due}d
                                                        late
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {formatMoney(row.amount)}
                                            </TableCell>
                                        </TableRow>
                                    ))}

                                    {collectionRows.length === 0 && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={3}
                                                className="py-8 text-center text-muted-foreground"
                                            >
                                                Nothing due in the next 7 days.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </SectionCard>
                    </>
                )}

                {currentTab === 'arrears' && (
                    <>
                        <section className="grid gap-4 sm:grid-cols-3">
                            <StatCard
                                title="Total arrears"
                                value={formatMoney(totalArrears)}
                                tone={totalArrears > 0 ? 'danger' : 'default'}
                            />
                            <StatCard
                                title="Loans in arrears"
                                value={String(arrearsLoans.length)}
                                hint={`of ${loans.length} loans on file`}
                            />
                            <StatCard
                                title="Worst DPD"
                                value={worstDpd > 0 ? `${worstDpd} days` : '—'}
                                hint="Days past due"
                                tone={worstDpd > 0 ? 'warning' : 'default'}
                            />
                        </section>

                        <SectionCard
                            title="Loans in arrears"
                            description="Sorted by the largest outstanding arrears."
                        >
                            <LoanReportTable
                                loans={arrearsLoans}
                                emptyMessage="No loans in arrears."
                            />
                        </SectionCard>
                    </>
                )}
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
