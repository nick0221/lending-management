import { Head, Link, router } from '@inertiajs/react';
import {
    Calculator,
    CheckCheck,
    FileText,
    Plus,
    Send,
    TriangleAlert,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { LoanRowActions } from '@/components/admin/loan-row-actions';
import { DataPagination } from '@/components/data-pagination';
import { EmptyState } from '@/components/empty-state';
import { FilterTabs } from '@/components/filter-tabs';
import { LoanStatusBadge } from '@/components/loan-status-badge';
import { PageHeader } from '@/components/page-header';
import { SearchInput } from '@/components/search-input';
import { SortableHeader } from '@/components/sortable-header';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
    bulkApprove as bulkApproveLoans,
    calculator as loanCalculator,
    create as createLoan,
    index as loansIndex,
    remind as remindLoans,
} from '@/routes/admin/loans';
import type { LoanListItem, LoanStatusOption, Paginated } from '@/types';

/**
 * Only open loans with unpaid, past-due installments receive reminders.
 */
function isRemindable(loan: LoanListItem): boolean {
    return (
        loan.days_past_due > 0 &&
        (loan.status === 'active' || loan.status === 'defaulted')
    );
}

/**
 * Only pending loans can be approved.
 */
function isApprovable(loan: LoanListItem): boolean {
    return loan.can.approve && loan.status === 'pending';
}

/**
 * A row is selectable when at least one bulk action applies to it.
 */
function isActionable(loan: LoanListItem): boolean {
    return isApprovable(loan) || isRemindable(loan);
}

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
    const [selected, setSelected] = useState<number[]>([]);
    const [approveOpen, setApproveOpen] = useState(false);
    const [approving, setApproving] = useState(false);
    const [remindOpen, setRemindOpen] = useState(false);
    const [reminding, setReminding] = useState(false);

    useEffect(() => {
        setSelected((current) =>
            current.filter((id) => loans.data.some((loan) => loan.id === id)),
        );
    }, [loans]);

    const approvableIds = loans.data
        .filter(isApprovable)
        .map((loan) => loan.id);
    const remindableIds = loans.data
        .filter(isRemindable)
        .map((loan) => loan.id);
    const actionableIds = [...new Set([...approvableIds, ...remindableIds])];
    const allActionableSelected =
        actionableIds.length > 0 &&
        actionableIds.every((id) => selected.includes(id));
    const someActionableSelected =
        selected.length > 0 && !allActionableSelected;
    const showSelection = actionableIds.length > 0;
    const selectedApprovableIds = selected.filter((id) =>
        approvableIds.includes(id),
    );
    const selectedRemindableIds = selected.filter((id) =>
        remindableIds.includes(id),
    );
    const hasApprovableSelection = selectedApprovableIds.length > 0;
    const hasRemindableSelection = selectedRemindableIds.length > 0;

    const submitApprovals = () => {
        router.post(
            bulkApproveLoans().url,
            { loans: selectedApprovableIds },
            {
                preserveScroll: true,
                onStart: () => setApproving(true),
                onFinish: () => setApproving(false),
                onSuccess: () => {
                    setApproveOpen(false);
                    setSelected((current) =>
                        current.filter(
                            (id) => !selectedApprovableIds.includes(id),
                        ),
                    );
                },
            },
        );
    };

    const submitReminders = () => {
        router.post(
            remindLoans().url,
            { loans: selectedRemindableIds },
            {
                preserveScroll: true,
                onStart: () => setReminding(true),
                onFinish: () => setReminding(false),
                onSuccess: () => {
                    setRemindOpen(false);
                    setSelected((current) =>
                        current.filter(
                            (id) => !selectedRemindableIds.includes(id),
                        ),
                    );
                },
            },
        );
    };

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
                        <div className="flex flex-wrap items-center justify-end gap-2">
                            <Button variant="outline" asChild>
                                <Link href={loanCalculator()}>
                                    <Calculator /> Calculator
                                </Link>
                            </Button>

                            <Button asChild>
                                <Link href={createLoan()}>
                                    <Plus /> New loan
                                </Link>
                            </Button>
                        </div>
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

                {selected.length > 0 && (
                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-primary/30 bg-primary/5 px-4 py-3">
                        <p className="text-sm font-medium">
                            {selected.length} loan
                            {selected.length === 1 ? '' : 's'} selected
                        </p>

                        <div className="flex flex-wrap items-center gap-2">
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => setSelected([])}
                            >
                                Clear
                            </Button>

                            {hasApprovableSelection && (
                                <Button
                                    size="sm"
                                    onClick={() => setApproveOpen(true)}
                                >
                                    <CheckCheck /> Approve{' '}
                                    {selectedApprovableIds.length}
                                </Button>
                            )}

                            {hasRemindableSelection && (
                                <Button
                                    size="sm"
                                    variant={
                                        hasApprovableSelection
                                            ? 'outline'
                                            : 'default'
                                    }
                                    onClick={() => setRemindOpen(true)}
                                >
                                    <Send /> Send reminders
                                </Button>
                            )}
                        </div>
                    </div>
                )}

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
                                    {showSelection && (
                                        <TableHead className="w-10">
                                            <Checkbox
                                                checked={
                                                    allActionableSelected
                                                        ? true
                                                        : someActionableSelected
                                                          ? 'indeterminate'
                                                          : false
                                                }
                                                onCheckedChange={(checked) =>
                                                    setSelected(
                                                        checked === true
                                                            ? actionableIds
                                                            : [],
                                                    )
                                                }
                                                aria-label="Select all actionable loans on this page"
                                            />
                                        </TableHead>
                                    )}
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
                                        {showSelection && (
                                            <TableCell>
                                                {isActionable(loan) ? (
                                                    <Checkbox
                                                        checked={selected.includes(
                                                            loan.id,
                                                        )}
                                                        onCheckedChange={(
                                                            checked,
                                                        ) =>
                                                            setSelected(
                                                                (current) =>
                                                                    checked ===
                                                                    true
                                                                        ? [
                                                                              ...current,
                                                                              loan.id,
                                                                          ]
                                                                        : current.filter(
                                                                              (
                                                                                  id,
                                                                              ) =>
                                                                                  id !==
                                                                                  loan.id,
                                                                          ),
                                                            )
                                                        }
                                                        aria-label={`Select loan ${loan.id} for bulk actions`}
                                                    />
                                                ) : null}
                                            </TableCell>
                                        )}
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

                <AlertDialog open={approveOpen} onOpenChange={setApproveOpen}>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>
                                Approve {selectedApprovableIds.length} loan
                                {selectedApprovableIds.length === 1 ? '' : 's'}?
                            </AlertDialogTitle>

                            <AlertDialogDescription>
                                This builds the repayment schedule for each
                                selected pending loan. Approved loans can be
                                disbursed next. Loans that are no longer pending
                                are skipped.
                            </AlertDialogDescription>
                        </AlertDialogHeader>

                        <AlertDialogFooter>
                            <AlertDialogCancel disabled={approving}>
                                Cancel
                            </AlertDialogCancel>

                            <Button
                                onClick={submitApprovals}
                                disabled={approving}
                            >
                                <CheckCheck />
                                {approving ? 'Approving…' : 'Approve loans'}
                            </Button>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>

                <AlertDialog open={remindOpen} onOpenChange={setRemindOpen}>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>
                                Send overdue reminders?
                            </AlertDialogTitle>

                            <AlertDialogDescription>
                                {selectedRemindableIds.length} overdue loan
                                {selectedRemindableIds.length === 1
                                    ? ''
                                    : 's'}{' '}
                                selected. Each eligible borrower will be emailed
                                a summary of their overdue installments.
                                Borrowers who opted out or already received a
                                notice today are skipped.
                            </AlertDialogDescription>
                        </AlertDialogHeader>

                        <AlertDialogFooter>
                            <AlertDialogCancel disabled={reminding}>
                                Cancel
                            </AlertDialogCancel>

                            <Button
                                onClick={submitReminders}
                                disabled={reminding}
                            >
                                <Send />
                                {reminding ? 'Sending…' : 'Send reminders'}
                            </Button>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
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
