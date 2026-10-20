import { Head, Link } from '@inertiajs/react';
import { FileText, Plus, TriangleAlert } from 'lucide-react';
import { LoanRowActions } from '@/components/admin/loan-row-actions';
import { DataPagination } from '@/components/data-pagination';
import { EmptyState } from '@/components/empty-state';
import { FilterTabs } from '@/components/filter-tabs';
import { LoanStatusBadge } from '@/components/loan-status-badge';
import { PageHeader } from '@/components/page-header';
import { SearchInput } from '@/components/search-input';
import { SortableHeader } from '@/components/sortable-header';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Toggle } from '@/components/ui/toggle';
import { dashboard as adminDashboard } from '@/routes/admin';
import {
    create as createLoan,
    index as loansIndex,
} from '@/routes/admin/loans';
import type { LoanListItem, LoanStatusOption, Paginated } from '@/types';

export default function LoansIndex({
    loans,
    statuses,
    currentStatus,
    overdueOnly,
    counts,
    search,
    currentSort,
    currentDirection,
}: {
    loans: Paginated<LoanListItem>;
    statuses: LoanStatusOption[];
    currentStatus: string | null;
    overdueOnly: boolean;
    counts: Record<string, number>;
    search: string;
    currentSort: string | null;
    currentDirection: 'asc' | 'desc';
}) {
    const withFilters = (overrides: {
        status?: string | null;
        overdue?: boolean;
        sort?: string;
        direction?: 'asc' | 'desc';
    }) => {
        const status = overrides.status ?? currentStatus ?? '';
        const overdue = overrides.overdue ?? overdueOnly;
        const sort = overrides.sort ?? currentSort ?? '';
        const direction = overrides.direction ?? currentDirection;

        return loansIndex({
            query: {
                ...(status ? { status } : {}),
                ...(overdue ? { overdue: '1' } : {}),
                ...(search ? { search } : {}),
                ...(sort ? { sort, direction } : {}),
            },
        });
    };

    return (
        <>
            <Head title="Loans" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Loans"
                    description="Originate and review loan applications."
                    actions={
                        <Button asChild>
                            <Link href={createLoan()}>
                                <Plus /> New loan
                            </Link>
                        </Button>
                    }
                />

                <div className="flex flex-wrap items-center gap-3">
                    <SearchInput
                        value={search}
                        placeholder="Search borrower or loan #"
                        label="Search loans"
                    />

                    <Toggle
                        asChild
                        variant="outline"
                        pressed={overdueOnly}
                        className="ml-auto h-9 gap-2 px-3"
                    >
                        <Link
                            href={withFilters({ overdue: !overdueOnly })}
                            preserveScroll
                            prefetch
                        >
                            <TriangleAlert />
                            Overdue only
                        </Link>
                    </Toggle>
                </div>

                <FilterTabs
                    tabs={[
                        {
                            value: 'all',
                            label: 'All',
                            count: counts.all,
                            href: withFilters({ status: '' }),
                        },
                        ...statuses.map((status) => ({
                            value: status.value,
                            label: status.label,
                            count: counts[status.value] ?? 0,
                            href: withFilters({ status: status.value }),
                        })),
                    ]}
                    value={currentStatus ?? 'all'}
                    label="Filter loans by status"
                    className="min-w-0"
                />

                <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    {loans.data.length === 0 ? (
                        <EmptyState
                            icon={FileText}
                            title="No loans found"
                            description={
                                search
                                    ? `No loans match "${search}". Try another term or clear the search.`
                                    : currentStatus || overdueOnly
                                      ? 'No loans match these filters. Try another status or turn off the overdue filter.'
                                      : 'Originate the first loan to start building the portfolio.'
                            }
                            action={
                                <Button asChild size="sm">
                                    <Link href={createLoan()}>
                                        <Plus /> New loan
                                    </Link>
                                </Button>
                            }
                        />
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Borrower</TableHead>
                                    <TableHead>Product</TableHead>
                                    <TableHead>
                                        <SortableHeader
                                            label="Principal"
                                            column="principal"
                                            currentSort={currentSort}
                                            direction={currentDirection}
                                            href={(direction) =>
                                                withFilters({
                                                    sort: 'principal',
                                                    direction,
                                                })
                                            }
                                        />
                                    </TableHead>
                                    <TableHead>Terms</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>
                                        <SortableHeader
                                            label="Arrears"
                                            column="arrears"
                                            currentSort={currentSort}
                                            direction={currentDirection}
                                            href={(direction) =>
                                                withFilters({
                                                    sort: 'arrears',
                                                    direction,
                                                })
                                            }
                                        />
                                    </TableHead>
                                    <TableHead>
                                        <SortableHeader
                                            label="Applied"
                                            column="applied_at"
                                            currentSort={currentSort}
                                            direction={currentDirection}
                                            href={(direction) =>
                                                withFilters({
                                                    sort: 'applied_at',
                                                    direction,
                                                })
                                            }
                                        />
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>

                            <TableBody>
                                {loans.data.map((loan) => (
                                    <TableRow key={loan.id}>
                                        <TableCell className="font-medium">
                                            {loan.borrower_name}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {loan.loan_product_name}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {Number(loan.principal).toFixed(2)}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {loan.term_months} mo ·{' '}
                                            {loan.repayment_frequency_label}
                                        </TableCell>
                                        <TableCell>
                                            <LoanStatusBadge
                                                status={loan.status}
                                            />
                                        </TableCell>
                                        <TableCell>
                                            {loan.days_past_due > 0 ? (
                                                <div className="flex flex-col">
                                                    <span className="font-medium text-destructive">
                                                        {Number(
                                                            loan.arrears,
                                                        ).toFixed(2)}
                                                    </span>
                                                    <span className="text-xs text-muted-foreground">
                                                        {loan.days_past_due} day
                                                        {loan.days_past_due ===
                                                        1
                                                            ? ''
                                                            : 's'}{' '}
                                                        late
                                                    </span>
                                                </div>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    —
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {loan.applied_at
                                                ? new Date(
                                                      loan.applied_at,
                                                  ).toLocaleDateString()
                                                : '—'}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center justify-end">
                                                <LoanRowActions loan={loan} />
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </div>

                <DataPagination paginator={loans} />
            </div>
        </>
    );
}

LoansIndex.layout = {
    breadcrumbs: [
        {
            title: 'Administration',
            href: adminDashboard(),
        },
        {
            title: 'Loans',
            href: loansIndex(),
        },
    ],
};
