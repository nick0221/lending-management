import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ChevronsUpDown,
    Download,
    Printer,
} from 'lucide-react';
import { useState } from 'react';
import { ReportBarChart } from '@/components/admin/report-bar-chart';
import type { ReportBarDatum } from '@/components/admin/report-bar-chart';
import { DateRangePicker } from '@/components/date-range-picker';
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
import { cn } from '@/lib/utils';
import { dashboard as adminDashboard } from '@/routes/admin';
import { show as showBorrower } from '@/routes/admin/borrowers';
import { show as showLoan } from '@/routes/admin/loans';
import {
    download,
    index as reportsIndex,
    print as printReport,
} from '@/routes/admin/reports';
import type {
    AgingBuckets,
    Collections,
    PortfolioRisk,
    PortfolioSummary,
    ReportFilters,
    ReportLoanRow,
} from '@/types';

const agingRows: {
    key: keyof AgingBuckets;
    label: string;
    barClass: string;
}[] = [
    { key: 'current', label: 'Current', barClass: 'bg-emerald-500' },
    { key: 'days_1_30', label: '1 – 30 days', barClass: 'bg-amber-500' },
    { key: 'days_31_60', label: '31 – 60 days', barClass: 'bg-orange-500' },
    { key: 'days_61_90', label: '61 – 90 days', barClass: 'bg-red-500' },
    { key: 'days_over_90', label: 'Over 90 days', barClass: 'bg-red-700' },
];

type ReportsTab = 'portfolio' | 'aging' | 'collections' | 'arrears';

const REPORT_ROWS_PER_PAGE = 50;

type LoanSortKey =
    | 'id'
    | 'borrower'
    | 'principal'
    | 'paid'
    | 'outstanding'
    | 'arrears'
    | 'days_past_due';

type LoanSort = {
    key: LoanSortKey;
    direction: 'asc' | 'desc';
};

function loanSortValue(loan: ReportLoanRow, key: LoanSortKey): string | number {
    switch (key) {
        case 'borrower':
            return (loan.borrower ?? '').toLowerCase();
        case 'id':
            return loan.id;
        case 'principal':
            return loan.principal;
        case 'paid':
            return loan.paid;
        case 'outstanding':
            return loan.outstanding;
        case 'arrears':
            return loan.arrears;
        case 'days_past_due':
            return loan.days_past_due;
    }
}

function SortableHead({
    label,
    column,
    sort,
    onSort,
    align = 'left',
    className,
}: {
    label: string;
    column: LoanSortKey;
    sort: LoanSort | null;
    onSort: (column: LoanSortKey) => void;
    align?: 'left' | 'right';
    className?: string;
}) {
    const activeSort = sort?.key === column ? sort : null;
    const Icon = !activeSort
        ? ChevronsUpDown
        : activeSort.direction === 'asc'
          ? ArrowUp
          : ArrowDown;

    return (
        <TableHead
            aria-sort={
                activeSort
                    ? activeSort.direction === 'asc'
                        ? 'ascending'
                        : 'descending'
                    : 'none'
            }
            className={className}
        >
            <button
                type="button"
                onClick={() => onSort(column)}
                className={cn(
                    'inline-flex items-center gap-1 text-sm font-medium transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none',
                    align === 'right' && 'w-full justify-end',
                    activeSort ? 'text-foreground' : 'text-muted-foreground',
                )}
            >
                {label}
                <Icon className="size-3.5 shrink-0" aria-hidden />
            </button>
        </TableHead>
    );
}

function TableLoadMore({
    shown,
    total,
    onLoadMore,
}: {
    shown: number;
    total: number;
    onLoadMore: () => void;
}) {
    if (shown >= total) {
        return null;
    }

    return (
        <div className="flex flex-col items-center justify-between gap-3 border-t p-4 sm:flex-row">
            <p className="text-sm text-muted-foreground">
                Showing {shown} of {total}
            </p>

            <Button variant="outline" size="sm" onClick={onLoadMore}>
                Load more
            </Button>
        </div>
    );
}

function LoanReportTable({
    loans,
    emptyMessage,
}: {
    loans: ReportLoanRow[];
    emptyMessage: string;
}) {
    const [visibleCount, setVisibleCount] = useState(REPORT_ROWS_PER_PAGE);
    const [sort, setSort] = useState<LoanSort | null>(null);

    const sortedLoans = sort
        ? [...loans].sort((first, second) => {
              const left = loanSortValue(first, sort.key);
              const right = loanSortValue(second, sort.key);

              const comparison =
                  typeof left === 'string' && typeof right === 'string'
                      ? left.localeCompare(right)
                      : Number(left) - Number(right);

              if (comparison === 0) {
                  return first.id - second.id;
              }

              return sort.direction === 'asc' ? comparison : -comparison;
          })
        : loans;

    const visibleLoans = sortedLoans.slice(0, visibleCount);

    const toggleSort = (column: LoanSortKey) => {
        setVisibleCount(REPORT_ROWS_PER_PAGE);
        setSort((current) =>
            current?.key === column
                ? {
                      key: column,
                      direction: current.direction === 'asc' ? 'desc' : 'asc',
                  }
                : {
                      key: column,
                      direction: column === 'borrower' ? 'asc' : 'desc',
                  },
        );
    };

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
        <>
            {loans.length === 0 ? (
                <p className="py-8 text-center text-sm text-muted-foreground">
                    {emptyMessage}
                </p>
            ) : (
                <>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <SortableHead
                                    label="Loan"
                                    column="id"
                                    sort={sort}
                                    onSort={toggleSort}
                                />
                                <SortableHead
                                    label="Borrower"
                                    column="borrower"
                                    sort={sort}
                                    onSort={toggleSort}
                                    className="hidden xl:table-cell"
                                />
                                <TableHead className="hidden xl:table-cell">
                                    Product
                                </TableHead>
                                <TableHead className="hidden md:table-cell">
                                    Status
                                </TableHead>
                                <SortableHead
                                    label="Principal"
                                    column="principal"
                                    sort={sort}
                                    onSort={toggleSort}
                                    align="right"
                                    className="hidden xl:table-cell"
                                />
                                <SortableHead
                                    label="Paid"
                                    column="paid"
                                    sort={sort}
                                    onSort={toggleSort}
                                    align="right"
                                    className="hidden xl:table-cell"
                                />
                                <SortableHead
                                    label="Outstanding"
                                    column="outstanding"
                                    sort={sort}
                                    onSort={toggleSort}
                                    align="right"
                                />
                                <SortableHead
                                    label="Arrears"
                                    column="arrears"
                                    sort={sort}
                                    onSort={toggleSort}
                                    align="right"
                                />
                                <SortableHead
                                    label="DPD"
                                    column="days_past_due"
                                    sort={sort}
                                    onSort={toggleSort}
                                    align="right"
                                    className="hidden md:table-cell"
                                />
                            </TableRow>
                        </TableHeader>

                        <TableBody>
                            {visibleLoans.map((loan) => (
                                <TableRow key={loan.id}>
                                    <TableCell>
                                        <Link
                                            href={showLoan(loan.id)}
                                            className="font-medium hover:underline"
                                            prefetch
                                        >
                                            #{loan.id}
                                        </Link>
                                        <div className="mt-0.5 xl:hidden">
                                            <Link
                                                href={showBorrower(
                                                    loan.borrower_id,
                                                )}
                                                className="text-xs font-medium hover:underline"
                                                prefetch
                                            >
                                                {loan.borrower ?? '—'}
                                            </Link>
                                        </div>
                                        <div className="mt-1 flex flex-wrap items-center gap-2 md:hidden">
                                            <LoanStatusBadge
                                                status={loan.status}
                                            />
                                            {loan.product && (
                                                <span className="text-xs text-muted-foreground">
                                                    {loan.product}
                                                </span>
                                            )}
                                        </div>
                                        <div className="mt-0.5 hidden text-xs text-muted-foreground md:block xl:hidden">
                                            {loan.product ?? '—'}
                                        </div>
                                    </TableCell>
                                    <TableCell className="hidden xl:table-cell">
                                        <Link
                                            href={showBorrower(
                                                loan.borrower_id,
                                            )}
                                            className="hover:underline"
                                            prefetch
                                        >
                                            {loan.borrower ?? '—'}
                                        </Link>
                                    </TableCell>
                                    <TableCell className="hidden text-muted-foreground xl:table-cell">
                                        {loan.product ?? '—'}
                                    </TableCell>
                                    <TableCell className="hidden md:table-cell">
                                        <LoanStatusBadge status={loan.status} />
                                    </TableCell>
                                    <TableCell className="hidden text-right tabular-nums xl:table-cell">
                                        {formatMoney(loan.principal)}
                                    </TableCell>
                                    <TableCell className="hidden text-right text-muted-foreground tabular-nums xl:table-cell">
                                        {formatMoney(loan.paid)}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        <span className="font-medium">
                                            {formatMoney(loan.outstanding)}
                                        </span>
                                        {loan.outstanding_penalty > 0 && (
                                            <span className="block text-xs text-muted-foreground">
                                                incl.{' '}
                                                {formatMoney(
                                                    loan.outstanding_penalty,
                                                )}{' '}
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
                                            <span className="text-muted-foreground">
                                                —
                                            </span>
                                        )}
                                        {loan.days_past_due > 0 && (
                                            <span className="block text-xs text-muted-foreground md:hidden">
                                                {loan.days_past_due}d late
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell className="hidden text-right text-muted-foreground tabular-nums md:table-cell">
                                        {loan.days_past_due > 0
                                            ? loan.days_past_due
                                            : '—'}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>

                        <TableFooter>
                            <TableRow className="md:hidden">
                                <TableCell>Total</TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {formatMoney(totals.outstanding)}
                                </TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {formatMoney(totals.arrears)}
                                </TableCell>
                            </TableRow>
                            <TableRow className="hidden md:table-row xl:hidden">
                                <TableCell colSpan={2}>Total</TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {formatMoney(totals.outstanding)}
                                </TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {formatMoney(totals.arrears)}
                                </TableCell>
                                <TableCell />
                            </TableRow>
                            <TableRow className="hidden xl:table-row">
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
                    </Table>
                    <TableLoadMore
                        shown={visibleLoans.length}
                        total={loans.length}
                        onLoadMore={() =>
                            setVisibleCount(
                                (count) => count + REPORT_ROWS_PER_PAGE,
                            )
                        }
                    />
                </>
            )}
        </>
    );
}

export default function ReportsIndex({
    summary,
    aging,
    risk,
    collections,
    loans,
    currentTab,
    filters,
}: {
    summary: PortfolioSummary;
    aging: AgingBuckets;
    risk: PortfolioRisk;
    collections: Collections;
    loans: ReportLoanRow[];
    currentTab: ReportsTab;
    filters: ReportFilters;
}) {
    const agingTotal = agingRows.reduce(
        (total, row) => total + aging[row.key],
        0,
    );
    const [visibleCollectionCount, setVisibleCollectionCount] =
        useState(REPORT_ROWS_PER_PAGE);

    const share = (amount: number) =>
        agingTotal > 0 ? (amount / agingTotal) * 100 : 0;

    const periodQuery = {
        ...(filters.from ? { from: filters.from } : {}),
        ...(filters.to ? { to: filters.to } : {}),
    };

    const periodLabel =
        filters.from && filters.to
            ? `${formatDateOnly(filters.from)} – ${formatDateOnly(filters.to)}`
            : filters.from
              ? `from ${formatDateOnly(filters.from)}`
              : filters.to
                ? `until ${formatDateOnly(filters.to)}`
                : null;

    const agingChartData: ReportBarDatum[] = [
        {
            label: 'Current',
            value: aging.current,
            color: 'var(--chart-2)',
        },
        {
            label: '1 - 30 days',
            value: aging.days_1_30,
            color: 'var(--chart-5)',
        },
        {
            label: '31 - 60 days',
            value: aging.days_31_60,
            color: 'var(--chart-4)',
        },
        {
            label: '61 - 90 days',
            value: aging.days_61_90,
            color: 'var(--chart-3)',
        },
        {
            label: 'Over 90 days',
            value: aging.days_over_90,
            color: 'var(--destructive)',
        },
    ];

    const riskChartData: ReportBarDatum[] = [
        { label: 'PAR', value: risk.par.ratio, color: 'var(--chart-1)' },
        {
            label: 'PAR 30',
            value: risk.par30.ratio,
            color: 'var(--chart-5)',
        },
        {
            label: 'PAR 60',
            value: risk.par60.ratio,
            color: 'var(--chart-4)',
        },
        {
            label: 'PAR 90',
            value: risk.par90.ratio,
            color: 'var(--destructive)',
        },
    ];

    const collectionsChartData: ReportBarDatum[] = [
        {
            label: 'Due today',
            value: collections.due_today,
            color: 'var(--chart-2)',
        },
        {
            label: 'Due this week',
            value: collections.due_this_week,
            color: 'var(--chart-1)',
        },
        {
            label: 'Overdue',
            value: collections.overdue,
            color: 'var(--destructive)',
        },
    ];

    const collectionRows = [
        ...collections.overdue_installments,
        ...collections.upcoming,
    ].sort((a, b) => a.due_date.localeCompare(b.due_date));
    const visibleCollectionRows = collectionRows.slice(
        0,
        visibleCollectionCount,
    );

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
                    <a
                        href={
                            download(
                                { report: 'summary' },
                                { query: periodQuery },
                            ).url
                        }
                    >
                        Portfolio summary
                    </a>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <a
                        href={
                            download(
                                { report: 'loans' },
                                { query: periodQuery },
                            ).url
                        }
                    >
                        Loan report
                    </a>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <a
                        href={
                            download(
                                { report: 'arrears' },
                                { query: periodQuery },
                            ).url
                        }
                    >
                        Arrears
                    </a>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <a
                        href={
                            download(
                                { report: 'collections' },
                                { query: periodQuery },
                            ).url
                        }
                    >
                        Collections
                    </a>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );

    const actions = (
        <div className="flex flex-wrap gap-2">
            <DateRangePicker
                from={filters.from}
                to={filters.to}
                align="start"
                onChange={(from, to) =>
                    router.get(
                        reportsIndex().url,
                        {
                            ...(currentTab !== 'portfolio'
                                ? { tab: currentTab }
                                : {}),
                            ...(from ? { from } : {}),
                            ...(to ? { to } : {}),
                        },
                        { preserveScroll: true, preserveState: true },
                    )
                }
            />

            <Button variant="outline" size="sm" asChild>
                <a
                    href={printReport({ query: periodQuery }).url}
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    <Printer /> Print summary
                </a>
            </Button>

            {exportMenu}
        </div>
    );

    return (
        <>
            <Head title="Reports" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Reports"
                    description="Portfolio performance, collections and arrears. The date filter narrows the loan cohort by application date."
                    actions={actions}
                />

                <FilterTabs
                    tabs={[
                        {
                            value: 'portfolio',
                            label: 'Portfolio',
                            count: loans.length,
                            href: reportsIndex({ query: periodQuery }),
                        },
                        {
                            value: 'aging',
                            label: 'Aging',
                            href: reportsIndex({
                                query: { tab: 'aging', ...periodQuery },
                            }),
                        },
                        {
                            value: 'collections',
                            label: 'Collections',
                            count: collectionRows.length,
                            href: reportsIndex({
                                query: {
                                    tab: 'collections',
                                    ...periodQuery,
                                },
                            }),
                        },
                        {
                            value: 'arrears',
                            label: 'Arrears',
                            count: arrearsLoans.length,
                            href: reportsIndex({
                                query: { tab: 'arrears', ...periodQuery },
                            }),
                        },
                    ]}
                    value={currentTab}
                    label="Report sections"
                />

                <p className="-mt-2 text-sm text-muted-foreground">
                    {periodLabel
                        ? `Loans applied ${periodLabel}.`
                        : 'Showing all loans and borrowers.'}
                </p>

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
                            title="Portfolio at risk"
                            description="The share of outstanding balance that is overdue, by days past due threshold."
                        >
                            <div className="p-4">
                                <ReportBarChart
                                    data={riskChartData}
                                    valueFormatter={formatPercent}
                                    valueLabel="Share"
                                    ariaLabel="Portfolio at risk by days past due"
                                />
                            </div>
                        </SectionCard>

                        <SectionCard
                            title="Loan report"
                            description={`${loans.length} loan${
                                loans.length === 1 ? '' : 's'
                            } ${
                                periodLabel
                                    ? 'applied in this period'
                                    : 'on file'
                            }. Sort any column to reorder; totals still cover every loan.`}
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
                        description="Outstanding balance by days past due. The chart compares bucket totals; the table adds each bucket's share."
                    >
                        <div className="border-b p-4">
                            <ReportBarChart
                                data={agingChartData}
                                ariaLabel="Outstanding balance by aging bucket"
                            />
                        </div>

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
                                        <TableCell>
                                            <div className="flex items-center justify-end gap-3">
                                                <div
                                                    className="h-2 w-full max-w-32 overflow-hidden rounded-full bg-muted"
                                                    aria-hidden
                                                >
                                                    <div
                                                        className={cn(
                                                            'h-full rounded-full',
                                                            row.barClass,
                                                        )}
                                                        style={{
                                                            width: `${share(aging[row.key])}%`,
                                                        }}
                                                    />
                                                </div>

                                                <span className="w-16 text-right text-muted-foreground tabular-nums">
                                                    {formatPercent(
                                                        share(aging[row.key]),
                                                    )}
                                                </span>
                                            </div>
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
                            title="Collection outlook"
                            description="Amounts due today and over the next seven days, compared with the current overdue balance."
                        >
                            <div className="p-4">
                                <ReportBarChart
                                    data={collectionsChartData}
                                    ariaLabel="Collections due today, this week and overdue"
                                />
                            </div>
                        </SectionCard>

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
                                    {visibleCollectionRows.map((row) => (
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

                            <TableLoadMore
                                shown={visibleCollectionRows.length}
                                total={collectionRows.length}
                                onLoadMore={() =>
                                    setVisibleCollectionCount(
                                        (count) => count + REPORT_ROWS_PER_PAGE,
                                    )
                                }
                            />
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
                                hint={`of ${loans.length} ${
                                    periodLabel ? 'cohort' : 'loans on file'
                                }`}
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
                            description="Largest arrears first by default; sort any column to reorder. Totals still cover every loan in arrears."
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
