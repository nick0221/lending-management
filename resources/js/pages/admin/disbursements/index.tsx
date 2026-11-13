import { Head, Link, router } from '@inertiajs/react';
import { Banknote, Download, Landmark, Wallet, X } from 'lucide-react';
import { DataPagination } from '@/components/data-pagination';
import { DateRangePicker } from '@/components/date-range-picker';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { SearchInput } from '@/components/search-input';
import { SortableHeader } from '@/components/sortable-header';
import StatCard from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDateOnly, formatMoney } from '@/lib/format';
import { dashboard as adminDashboard } from '@/routes/admin';
import { show as showBorrower } from '@/routes/admin/borrowers';
import {
    exportMethod as exportDisbursements,
    index as disbursementsIndex,
} from '@/routes/admin/disbursements';
import { index as adminLoans, show as showLoan } from '@/routes/admin/loans';
import type {
    DisbursementMethod,
    DisbursementMethodOption,
    DisbursementRegisterRow,
    Paginated,
    SelectOption,
} from '@/types';

type DisbursementSummary = {
    count: number;
    total: string;
    average: string;
};

/**
 * Every payout recorded across the book, filterable by method, date, borrower
 * and loan so finance can reconcile without opening loans one by one.
 */
export default function DisbursementsIndex({
    disbursements,
    summary,
    methods,
    currentMethod,
    recorders,
    currentRecorder,
    dateFrom,
    dateTo,
    currentBorrower,
    currentLoan,
    search,
    currentSort,
    currentDirection,
}: {
    disbursements: Paginated<DisbursementRegisterRow>;
    summary: DisbursementSummary;
    methods: DisbursementMethodOption[];
    currentMethod: DisbursementMethod | null;
    recorders: SelectOption<number>[];
    currentRecorder: number | null;
    dateFrom: string | null;
    dateTo: string | null;
    currentBorrower: { id: number; name: string } | null;
    currentLoan: { id: number; label: string } | null;
    search: string;
    currentSort: string;
    currentDirection: 'asc' | 'desc';
}) {
    const withFilters = (overrides: {
        method?: DisbursementMethod | null;
        recordedBy?: number | null;
        borrower?: number | null;
        loan?: number | null;
        from?: string | null;
        to?: string | null;
        sort?: string;
        direction?: 'asc' | 'desc';
    }) => {
        const method =
            overrides.method !== undefined ? overrides.method : currentMethod;
        const recordedBy =
            overrides.recordedBy !== undefined
                ? overrides.recordedBy
                : currentRecorder;
        const borrower =
            overrides.borrower !== undefined
                ? overrides.borrower
                : (currentBorrower?.id ?? null);
        const loan =
            overrides.loan !== undefined
                ? overrides.loan
                : (currentLoan?.id ?? null);
        const from = overrides.from !== undefined ? overrides.from : dateFrom;
        const to = overrides.to !== undefined ? overrides.to : dateTo;
        const sort = overrides.sort ?? currentSort;
        const direction = overrides.direction ?? currentDirection;

        return disbursementsIndex({
            query: {
                ...(method ? { method } : {}),
                ...(recordedBy ? { recorded_by: recordedBy } : {}),
                ...(borrower ? { borrower } : {}),
                ...(loan ? { loan } : {}),
                ...(from ? { from } : {}),
                ...(to ? { to } : {}),
                ...(search ? { search } : {}),
                ...(sort !== 'disbursed_at' || direction !== 'desc'
                    ? { sort, direction }
                    : {}),
                ...(disbursements.per_page !== 15
                    ? { per_page: disbursements.per_page }
                    : {}),
            },
        });
    };

    const navigate = (url: string) => {
        router.get(url, {}, { preserveScroll: true, preserveState: true });
    };

    const changeMethod = (value: string) => {
        navigate(
            withFilters({
                method: value === 'all' ? null : (value as DisbursementMethod),
            }).url,
        );
    };

    const changeRecorder = (value: string) => {
        navigate(
            withFilters({
                recordedBy: value === 'all' ? null : Number(value),
            }).url,
        );
    };

    const changeRange = (from: string | null, to: string | null) => {
        navigate(withFilters({ from, to }).url);
    };

    const clearFilters = disbursementsIndex({
        query:
            disbursements.per_page !== 15
                ? { per_page: disbursements.per_page }
                : {},
    });

    const exportUrl = exportDisbursements({
        query: {
            ...(currentMethod ? { method: currentMethod } : {}),
            ...(currentRecorder ? { recorded_by: currentRecorder } : {}),
            ...(currentBorrower ? { borrower: currentBorrower.id } : {}),
            ...(currentLoan ? { loan: currentLoan.id } : {}),
            ...(dateFrom ? { from: dateFrom } : {}),
            ...(dateTo ? { to: dateTo } : {}),
            ...(search ? { search } : {}),
            ...(currentSort !== 'disbursed_at' || currentDirection !== 'desc'
                ? { sort: currentSort, direction: currentDirection }
                : {}),
        },
    });

    const hasFilters =
        search !== '' ||
        currentMethod !== null ||
        currentRecorder !== null ||
        currentBorrower !== null ||
        currentLoan !== null ||
        dateFrom !== null ||
        dateTo !== null;

    return (
        <>
            <Head title="Disbursements" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Disbursements"
                    description={
                        currentLoan
                            ? `Payouts recorded for ${currentLoan.label}, newest first.`
                            : currentBorrower
                              ? `Payouts recorded for ${currentBorrower.name}, newest first.`
                              : 'Every payout recorded against the book, newest first.'
                    }
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <a href={exportUrl.url}>
                                    <Download /> Export CSV
                                </a>
                            </Button>

                            <Button variant="outline" asChild>
                                <Link href={adminLoans()}>
                                    <Wallet /> All loans
                                </Link>
                            </Button>
                        </>
                    }
                />

                <div className="grid gap-4 sm:grid-cols-3">
                    <StatCard
                        title="Payouts"
                        value={summary.count}
                        hint="In the current filters"
                        icon={Banknote}
                    />
                    <StatCard
                        title="Disbursed"
                        value={formatMoney(summary.total)}
                        hint="Total paid out"
                        icon={Landmark}
                        tone="success"
                    />
                    <StatCard
                        title="Average"
                        value={formatMoney(summary.average)}
                        hint="Per payout"
                        icon={Wallet}
                    />
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <SearchInput
                        value={search}
                        placeholder="Search borrower, phone, ID or reference"
                        label="Search disbursements"
                    />

                    <Select
                        value={currentMethod ?? 'all'}
                        onValueChange={changeMethod}
                    >
                        <SelectTrigger
                            className="h-9 min-w-0 flex-1 sm:w-[170px] sm:flex-none"
                            aria-label="Filter by disbursement method"
                        >
                            <SelectValue placeholder="All methods">
                                {currentMethod
                                    ? methods.find(
                                          (method) =>
                                              method.value === currentMethod,
                                      )?.label
                                    : 'All methods'}
                            </SelectValue>
                        </SelectTrigger>

                        <SelectContent>
                            <SelectItem value="all">All methods</SelectItem>

                            {methods.map((method) => (
                                <SelectItem
                                    key={method.value}
                                    value={method.value}
                                >
                                    {method.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    {recorders.length > 1 && (
                        <Select
                            value={
                                currentRecorder
                                    ? String(currentRecorder)
                                    : 'all'
                            }
                            onValueChange={changeRecorder}
                        >
                            <SelectTrigger
                                className="h-9 min-w-0 flex-1 sm:w-[190px] sm:flex-none"
                                aria-label="Filter by staff member"
                            >
                                <SelectValue placeholder="All staff">
                                    {currentRecorder
                                        ? recorders.find(
                                              (recorder) =>
                                                  recorder.value ===
                                                  currentRecorder,
                                          )?.label
                                        : 'All staff'}
                                </SelectValue>
                            </SelectTrigger>

                            <SelectContent>
                                <SelectItem value="all">All staff</SelectItem>

                                {recorders.map((recorder) => (
                                    <SelectItem
                                        key={recorder.value}
                                        value={String(recorder.value)}
                                    >
                                        {recorder.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}

                    <DateRangePicker
                        from={dateFrom}
                        to={dateTo}
                        onChange={changeRange}
                    />

                    {currentBorrower && (
                        <div className="flex items-center gap-2 rounded-full border bg-muted/50 py-1 pr-1 pl-3 text-sm">
                            <span className="text-muted-foreground">
                                Borrower
                            </span>

                            <span className="font-medium">
                                {currentBorrower.name}
                            </span>

                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-6"
                                asChild
                            >
                                <Link
                                    href={withFilters({ borrower: null })}
                                    aria-label="Clear the borrower filter"
                                    title="Clear the borrower filter"
                                    preserveScroll
                                >
                                    <X />
                                </Link>
                            </Button>
                        </div>
                    )}

                    {currentLoan && (
                        <div className="flex items-center gap-2 rounded-full border bg-muted/50 py-1 pr-1 pl-3 text-sm">
                            <span className="font-medium">
                                {currentLoan.label}
                            </span>

                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-6"
                                asChild
                            >
                                <Link
                                    href={withFilters({ loan: null })}
                                    aria-label="Clear the loan filter"
                                    title="Clear the loan filter"
                                    preserveScroll
                                >
                                    <X />
                                </Link>
                            </Button>
                        </div>
                    )}
                </div>

                <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    {disbursements.data.length === 0 ? (
                        <EmptyState
                            icon={Banknote}
                            title="No disbursements found"
                            description={
                                currentLoan
                                    ? `No payouts are recorded for ${currentLoan.label}. Clear the loan filter to see the whole register.`
                                    : currentBorrower
                                      ? `No payouts are recorded for ${currentBorrower.name}. Clear the borrower filter to see the whole register.`
                                      : hasFilters
                                        ? 'No payouts match these filters. Try another method or date range.'
                                        : 'Disburse a loan and the payout appears here.'
                            }
                            action={
                                hasFilters && (
                                    <Button asChild size="sm" variant="outline">
                                        <Link href={clearFilters}>
                                            <Banknote /> Clear filters
                                        </Link>
                                    </Button>
                                )
                            }
                        />
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>
                                        <SortableHeader
                                            label="Date"
                                            column="disbursed_at"
                                            currentSort={currentSort}
                                            direction={currentDirection}
                                            href={(direction) =>
                                                withFilters({
                                                    sort: 'disbursed_at',
                                                    direction,
                                                })
                                            }
                                        />
                                    </TableHead>
                                    <TableHead>Loan</TableHead>
                                    <TableHead className="hidden md:table-cell">
                                        Method
                                    </TableHead>
                                    <TableHead className="hidden lg:table-cell">
                                        Reference
                                    </TableHead>
                                    <TableHead className="text-right">
                                        <div className="flex justify-end">
                                            <SortableHeader
                                                label="Amount"
                                                column="amount"
                                                currentSort={currentSort}
                                                direction={currentDirection}
                                                href={(direction) =>
                                                    withFilters({
                                                        sort: 'amount',
                                                        direction,
                                                    })
                                                }
                                            />
                                        </div>
                                    </TableHead>
                                    <TableHead className="hidden xl:table-cell">
                                        Recorded by
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>

                            <TableBody>
                                {disbursements.data.map((disbursement) => (
                                    <TableRow key={disbursement.id}>
                                        <TableCell className="whitespace-nowrap">
                                            {formatDateOnly(
                                                disbursement.disbursed_at,
                                            )}

                                            <div className="text-xs text-muted-foreground md:hidden">
                                                {disbursement.method_label}
                                            </div>
                                        </TableCell>

                                        <TableCell>
                                            <Link
                                                href={showLoan(
                                                    disbursement.loan_id,
                                                    {
                                                        query: {
                                                            tab: 'disbursements',
                                                        },
                                                    },
                                                )}
                                                className="font-medium whitespace-nowrap hover:underline"
                                                prefetch
                                            >
                                                Loan #{disbursement.loan_id}
                                            </Link>

                                            <div className="text-sm">
                                                <Link
                                                    href={showBorrower(
                                                        disbursement.borrower_id,
                                                    )}
                                                    className="underline-offset-4 hover:underline"
                                                    prefetch
                                                >
                                                    {disbursement.borrower_name}
                                                </Link>
                                            </div>

                                            <div className="text-xs text-muted-foreground">
                                                {disbursement.borrower_phone}
                                            </div>
                                        </TableCell>

                                        <TableCell className="hidden md:table-cell">
                                            {disbursement.method_label}
                                        </TableCell>

                                        <TableCell className="hidden lg:table-cell">
                                            {disbursement.reference ?? '—'}
                                        </TableCell>

                                        <TableCell className="text-right font-medium tabular-nums">
                                            {formatMoney(disbursement.amount)}
                                        </TableCell>

                                        <TableCell className="hidden text-muted-foreground xl:table-cell">
                                            {disbursement.recorded_by ?? '—'}
                                        </TableCell>

                                        <TableCell>
                                            <div className="flex justify-end">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    asChild
                                                >
                                                    <Link
                                                        href={showLoan(
                                                            disbursement.loan_id,
                                                            {
                                                                query: {
                                                                    tab: 'disbursements',
                                                                },
                                                            },
                                                        )}
                                                        prefetch
                                                    >
                                                        Open loan
                                                    </Link>
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </div>

                <DataPagination paginator={disbursements} />
            </div>
        </>
    );
}

DisbursementsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Administration',
            href: adminDashboard(),
        },
        {
            title: 'Disbursements',
            href: disbursementsIndex(),
        },
    ],
};
