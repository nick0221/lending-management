import { Head, Link, router } from '@inertiajs/react';
import {
    Download,
    HandCoins,
    Receipt,
    RotateCcw,
    Wallet,
    X,
} from 'lucide-react';
import { DataPagination } from '@/components/data-pagination';
import { DateRangePicker } from '@/components/date-range-picker';
import { EmptyState } from '@/components/empty-state';
import { FilterTabs } from '@/components/filter-tabs';
import { PageHeader } from '@/components/page-header';
import { SearchInput } from '@/components/search-input';
import { SortableHeader } from '@/components/sortable-header';
import StatCard from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
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
import { cn } from '@/lib/utils';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as adminLoans, show as showLoan } from '@/routes/admin/loans';
import {
    exportMethod as exportPayments,
    index as paymentsIndex,
} from '@/routes/admin/payments';
import { receipt as paymentReceipt } from '@/routes/admin/loans/payments';
import type {
    Paginated,
    PaymentMethod,
    PaymentMethodOption,
    PaymentRegisterRow,
} from '@/types';

type PaymentTab = 'all' | 'completed' | 'reversed';

type PaymentSummary = {
    count: number;
    received: string;
    reversed: number;
};

/**
 * Every repayment recorded across the book, filterable by status, method,
 * date and borrower so finance can reconcile without opening loans one by one.
 */
export default function PaymentsIndex({
    payments,
    counts,
    summary,
    methods,
    currentStatus,
    currentMethod,
    dateFrom,
    dateTo,
    currentBorrower,
    search,
    currentSort,
    currentDirection,
}: {
    payments: Paginated<PaymentRegisterRow>;
    counts: Record<PaymentTab, number>;
    summary: PaymentSummary;
    methods: PaymentMethodOption[];
    currentStatus: PaymentTab;
    currentMethod: PaymentMethod | null;
    dateFrom: string | null;
    dateTo: string | null;
    currentBorrower: { id: number; name: string } | null;
    search: string;
    currentSort: string;
    currentDirection: 'asc' | 'desc';
}) {
    const withFilters = (overrides: {
        status?: PaymentTab;
        method?: PaymentMethod | null;
        borrower?: number | null;
        from?: string | null;
        to?: string | null;
        sort?: string;
        direction?: 'asc' | 'desc';
    }) => {
        const status = overrides.status ?? currentStatus;
        const method =
            overrides.method !== undefined ? overrides.method : currentMethod;
        const borrower =
            overrides.borrower !== undefined
                ? overrides.borrower
                : (currentBorrower?.id ?? null);
        const from = overrides.from !== undefined ? overrides.from : dateFrom;
        const to = overrides.to !== undefined ? overrides.to : dateTo;
        const sort = overrides.sort ?? currentSort;
        const direction = overrides.direction ?? currentDirection;

        return paymentsIndex({
            query: {
                ...(status !== 'all' ? { status } : {}),
                ...(method ? { method } : {}),
                ...(borrower ? { borrower } : {}),
                ...(from ? { from } : {}),
                ...(to ? { to } : {}),
                ...(search ? { search } : {}),
                ...(sort !== 'payment_date' || direction !== 'desc'
                    ? { sort, direction }
                    : {}),
                ...(payments.per_page !== 15
                    ? { per_page: payments.per_page }
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
                method: value === 'all' ? null : (value as PaymentMethod),
            }).url,
        );
    };

    const changeRange = (from: string | null, to: string | null) => {
        navigate(withFilters({ from, to }).url);
    };

    const clearFilters = paymentsIndex({
        query: payments.per_page !== 15 ? { per_page: payments.per_page } : {},
    });

    const exportUrl = exportPayments({
        query: {
            ...(currentStatus !== 'all' ? { status: currentStatus } : {}),
            ...(currentMethod ? { method: currentMethod } : {}),
            ...(currentBorrower ? { borrower: currentBorrower.id } : {}),
            ...(dateFrom ? { from: dateFrom } : {}),
            ...(dateTo ? { to: dateTo } : {}),
            ...(search ? { search } : {}),
            ...(currentSort !== 'payment_date' || currentDirection !== 'desc'
                ? { sort: currentSort, direction: currentDirection }
                : {}),
        },
    });

    const hasFilters =
        search !== '' ||
        currentStatus !== 'all' ||
        currentMethod !== null ||
        currentBorrower !== null ||
        dateFrom !== null ||
        dateTo !== null;

    return (
        <>
            <Head title="Payments" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Payments"
                    description={
                        currentBorrower
                            ? `Repayments recorded for ${currentBorrower.name}, newest first.`
                            : 'Every repayment recorded against the book, newest first.'
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
                        title="Payments"
                        value={summary.count}
                        hint="In the current filters"
                        icon={Receipt}
                    />
                    <StatCard
                        title="Received"
                        value={formatMoney(summary.received)}
                        hint="Completed payments"
                        icon={HandCoins}
                        tone="success"
                    />
                    <StatCard
                        title="Reversed"
                        value={summary.reversed}
                        hint="Reversed payments"
                        icon={RotateCcw}
                        tone={summary.reversed > 0 ? 'warning' : 'default'}
                    />
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <SearchInput
                        value={search}
                        placeholder="Search borrower, phone, reference or loan #"
                        label="Search payments"
                    />

                    <Select
                        value={currentMethod ?? 'all'}
                        onValueChange={changeMethod}
                    >
                        <SelectTrigger
                            className="h-9 min-w-0 flex-1 sm:w-[170px] sm:flex-none"
                            aria-label="Filter by payment method"
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
                </div>

                <FilterTabs
                    tabs={[
                        {
                            value: 'all',
                            label: 'All',
                            count: counts.all,
                            href: withFilters({ status: 'all' }),
                        },
                        {
                            value: 'completed',
                            label: 'Completed',
                            count: counts.completed,
                            href: withFilters({ status: 'completed' }),
                        },
                        {
                            value: 'reversed',
                            label: 'Reversed',
                            count: counts.reversed,
                            href: withFilters({ status: 'reversed' }),
                        },
                    ]}
                    value={currentStatus}
                    label="Filter payments by status"
                    className="min-w-0"
                />

                <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    {payments.data.length === 0 ? (
                        <EmptyState
                            icon={Receipt}
                            title="No payments found"
                            description={
                                currentBorrower
                                    ? `No payments are recorded for ${currentBorrower.name}. Clear the borrower filter to see the whole register.`
                                    : hasFilters
                                      ? 'No payments match these filters. Try another method, date range or status.'
                                      : 'Record a repayment from a loan file and it appears here.'
                            }
                            action={
                                hasFilters && (
                                    <Button asChild size="sm" variant="outline">
                                        <Link href={clearFilters}>
                                            <RotateCcw /> Clear filters
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
                                            column="payment_date"
                                            currentSort={currentSort}
                                            direction={currentDirection}
                                            href={(direction) =>
                                                withFilters({
                                                    sort: 'payment_date',
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
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>

                            <TableBody>
                                {payments.data.map((payment) => (
                                    <TableRow
                                        key={payment.id}
                                        className={cn(
                                            payment.reversed &&
                                                'text-muted-foreground',
                                        )}
                                    >
                                        <TableCell className="whitespace-nowrap">
                                            {formatDateOnly(
                                                payment.payment_date,
                                            )}

                                            <div className="text-xs text-muted-foreground md:hidden">
                                                {payment.method_label}
                                            </div>
                                        </TableCell>

                                        <TableCell>
                                            <Link
                                                href={showLoan(
                                                    payment.loan_id,
                                                    {
                                                        query: {
                                                            tab: 'payments',
                                                        },
                                                    },
                                                )}
                                                className="font-medium whitespace-nowrap hover:underline"
                                                prefetch
                                            >
                                                Loan #{payment.loan_id}
                                            </Link>

                                            <div className="text-sm">
                                                {payment.borrower_name}
                                            </div>

                                            <div className="text-xs text-muted-foreground">
                                                {payment.borrower_phone}
                                            </div>
                                        </TableCell>

                                        <TableCell className="hidden md:table-cell">
                                            {payment.method_label}
                                        </TableCell>

                                        <TableCell className="hidden lg:table-cell">
                                            {payment.reference ?? '—'}
                                        </TableCell>

                                        <TableCell className="text-right font-medium tabular-nums">
                                            {formatMoney(payment.amount)}
                                        </TableCell>

                                        <TableCell className="hidden text-muted-foreground xl:table-cell">
                                            {payment.recorded_by ?? '—'}
                                        </TableCell>

                                        <TableCell>
                                            <Badge
                                                variant={
                                                    payment.reversed
                                                        ? 'destructive'
                                                        : 'outline'
                                                }
                                            >
                                                {payment.reversed
                                                    ? 'Reversed'
                                                    : 'Completed'}
                                            </Badge>

                                            {payment.reversed &&
                                                payment.reversed_at && (
                                                    <div className="mt-1 text-xs text-muted-foreground">
                                                        {formatDateOnly(
                                                            payment.reversed_at,
                                                        )}
                                                    </div>
                                                )}
                                        </TableCell>

                                        <TableCell>
                                            <div className="flex justify-end gap-2">
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    asChild
                                                >
                                                    <a
                                                        href={
                                                            paymentReceipt({
                                                                loan: payment.loan_id,
                                                                payment:
                                                                    payment.id,
                                                            }).url
                                                        }
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                    >
                                                        Receipt
                                                    </a>
                                                </Button>

                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    asChild
                                                >
                                                    <Link
                                                        href={showLoan(
                                                            payment.loan_id,
                                                            {
                                                                query: {
                                                                    tab: 'payments',
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

                <DataPagination paginator={payments} />
            </div>
        </>
    );
}

PaymentsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Administration',
            href: adminDashboard(),
        },
        {
            title: 'Payments',
            href: paymentsIndex(),
        },
    ],
};
