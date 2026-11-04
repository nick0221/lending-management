import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Download,
    HandCoins,
    PhoneCall,
    RotateCcw,
    TriangleAlert,
    UserRound,
    Users,
    Wallet,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { LogCollectionContactDialog } from '@/components/admin/log-collection-contact-dialog';
import { DataPagination } from '@/components/data-pagination';
import { EmptyState } from '@/components/empty-state';
import { FilterTabs } from '@/components/filter-tabs';
import { LoanStatusBadge } from '@/components/loan-status-badge';
import { PageHeader } from '@/components/page-header';
import { SearchInput } from '@/components/search-input';
import { SortableHeader } from '@/components/sortable-header';
import StatCard from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { Toggle } from '@/components/ui/toggle';
import { formatDateOnly, formatMoney } from '@/lib/format';
import { cn } from '@/lib/utils';
import {
    index as collectionsIndex,
    exportMethod as exportCollections,
    bulkOfficer as bulkAssignCollectionOfficers,
    officer as assignCollectionOfficer,
} from '@/routes/admin/collections';
import { dashboard as adminDashboard } from '@/routes/admin';
import { show as showBorrower } from '@/routes/admin/borrowers';
import { index as loansIndex, show as showLoan } from '@/routes/admin/loans';
import type {
    CollectionChannelOption,
    CollectionOutcomeOption,
    CollectionQueueRow,
    CollectionQueueSummary,
    Paginated,
} from '@/types';

type CollectionTab = 'all' | 'unassigned' | 'promised' | 'follow_up';

/**
 * The overdue book as a work queue: who owes what, who owns the loan, what was
 * promised and when to follow up.
 */
export default function CollectionsIndex({
    loans,
    counts,
    summary,
    officers,
    collectionChannels,
    collectionOutcomes,
    buckets,
    currentTab,
    currentOfficer,
    currentBucket,
    currentSort,
    currentDirection,
    search,
}: {
    loans: Paginated<CollectionQueueRow>;
    counts: Record<CollectionTab, number>;
    summary: CollectionQueueSummary;
    officers: { value: number; label: string }[];
    collectionChannels: CollectionChannelOption[];
    collectionOutcomes: CollectionOutcomeOption[];
    buckets: { value: string; label: string }[];
    currentTab: CollectionTab;
    currentOfficer: number | null;
    currentBucket: string | null;
    currentSort: string;
    currentDirection: 'asc' | 'desc';
    search: string;
}) {
    const [contactLoan, setContactLoan] = useState<CollectionQueueRow | null>(
        null,
    );
    const [selected, setSelected] = useState<number[]>([]);
    const [bulkOfficer, setBulkOfficer] = useState('');
    const { auth } = usePage().props;
    const isMyQueue = currentOfficer === auth.user.id;

    useEffect(() => {
        setSelected((current) =>
            current.filter((id) =>
                loans.data.some((loan) => loan.id === id && loan.can.assign),
            ),
        );
    }, [loans]);

    const selectableIds = loans.data
        .filter((loan) => loan.can.assign)
        .map((loan) => loan.id);
    const allSelected =
        selectableIds.length > 0 &&
        selectableIds.every((id) => selected.includes(id));
    const someSelected = selected.length > 0 && !allSelected;

    const submitBulkAssign = () => {
        if (selected.length === 0 || bulkOfficer === '') {
            return;
        }

        router.post(
            bulkAssignCollectionOfficers().url,
            {
                loans: selected,
                officer_id:
                    bulkOfficer === 'unassigned' ? null : Number(bulkOfficer),
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setSelected([]);
                    setBulkOfficer('');
                },
            },
        );
    };
    const withFilters = (overrides: {
        tab?: CollectionTab;
        officer?: number | null;
        bucket?: string | null;
        sort?: string;
        direction?: 'asc' | 'desc';
    }) => {
        const tab = overrides.tab ?? currentTab;
        const officer =
            overrides.officer !== undefined
                ? overrides.officer
                : currentOfficer;
        const bucket =
            overrides.bucket !== undefined ? overrides.bucket : currentBucket;
        const sort = overrides.sort ?? currentSort;
        const direction = overrides.direction ?? currentDirection;

        return collectionsIndex({
            query: {
                ...(tab !== 'all' ? { tab } : {}),
                ...(officer ? { officer } : {}),
                ...(bucket ? { bucket } : {}),
                ...(search ? { search } : {}),
                ...(sort !== 'arrears' || direction !== 'desc'
                    ? { sort, direction }
                    : {}),
                ...(loans.per_page !== 15 ? { per_page: loans.per_page } : {}),
            },
        });
    };

    const changeOfficerFilter = (value: string) => {
        router.get(
            withFilters({
                officer: value === 'all' ? null : Number(value),
            }).url,
            {},
            { preserveScroll: true, preserveState: true },
        );
    };

    const changeBucket = (value: string) => {
        router.get(
            withFilters({ bucket: value === 'all' ? null : value }).url,
            {},
            { preserveScroll: true, preserveState: true },
        );
    };

    const clearFilters = collectionsIndex({
        query: loans.per_page !== 15 ? { per_page: loans.per_page } : {},
    });

    const exportUrl = exportCollections({
        query: {
            ...(currentTab !== 'all' ? { tab: currentTab } : {}),
            ...(currentOfficer ? { officer: currentOfficer } : {}),
            ...(currentBucket ? { bucket: currentBucket } : {}),
            ...(search ? { search } : {}),
            ...(currentSort !== 'arrears' || currentDirection !== 'desc'
                ? { sort: currentSort, direction: currentDirection }
                : {}),
        },
    });

    const assignOfficer = (loan: CollectionQueueRow, value: string) => {
        router.patch(
            assignCollectionOfficer({ loan: loan.id }).url,
            { officer_id: value === 'unassigned' ? null : Number(value) },
            { preserveScroll: true, preserveState: true },
        );
    };

    const officerSelect = (loan: CollectionQueueRow) => (
        <Select
            value={
                loan.collection_officer_id
                    ? String(loan.collection_officer_id)
                    : 'unassigned'
            }
            onValueChange={(value) => assignOfficer(loan, value)}
            disabled={!loan.can.assign}
        >
            <SelectTrigger
                className="h-8 w-full md:w-[160px]"
                aria-label={`Collection officer for loan ${loan.id}`}
            >
                <SelectValue placeholder="Unassigned">
                    {loan.collection_officer ?? 'Unassigned'}
                </SelectValue>
            </SelectTrigger>

            <SelectContent>
                <SelectItem value="unassigned">Unassigned</SelectItem>

                {officers.map((officer) => (
                    <SelectItem
                        key={officer.value}
                        value={String(officer.value)}
                    >
                        {officer.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );

    const hasFilters =
        search !== '' ||
        currentTab !== 'all' ||
        currentOfficer !== null ||
        currentBucket !== null;

    return (
        <>
            <Head title="Collections" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Collections"
                    description="Work the overdue book: assign collectors, log promises and follow up."
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <a href={exportUrl.url}>
                                    <Download /> Export CSV
                                </a>
                            </Button>

                            <Button variant="outline" asChild>
                                <Link href={loansIndex()}>
                                    <Wallet /> All loans
                                </Link>
                            </Button>
                        </>
                    }
                />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        title="Overdue loans"
                        value={summary.loans}
                        hint="In the current filters"
                        icon={TriangleAlert}
                    />
                    <StatCard
                        title="Arrears"
                        value={formatMoney(summary.arrears)}
                        hint="Past-due balance"
                        tone={
                            Number(summary.arrears) > 0 ? 'danger' : 'default'
                        }
                        icon={HandCoins}
                    />
                    <StatCard
                        title="Promises to pay"
                        value={summary.promised}
                        hint="Open promises"
                        icon={PhoneCall}
                    />
                    <StatCard
                        title="Unassigned"
                        value={summary.unassigned}
                        hint="No collection officer"
                        icon={Users}
                    />
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <SearchInput
                        value={search}
                        placeholder="Search borrower, phone or loan #"
                        label="Search the collections queue"
                    />

                    <Select
                        value={currentOfficer ? String(currentOfficer) : 'all'}
                        onValueChange={changeOfficerFilter}
                    >
                        <SelectTrigger
                            className="h-9 min-w-0 flex-1 sm:w-[180px] sm:flex-none"
                            aria-label="Filter by collection officer"
                        >
                            <SelectValue placeholder="All officers">
                                {currentOfficer
                                    ? officers.find(
                                          (officer) =>
                                              officer.value === currentOfficer,
                                      )?.label
                                    : 'All officers'}
                            </SelectValue>
                        </SelectTrigger>

                        <SelectContent>
                            <SelectItem value="all">All officers</SelectItem>

                            {officers.map((officer) => (
                                <SelectItem
                                    key={officer.value}
                                    value={String(officer.value)}
                                >
                                    {officer.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={currentBucket ?? 'all'}
                        onValueChange={changeBucket}
                    >
                        <SelectTrigger
                            className="h-9 min-w-0 flex-1 sm:w-[160px] sm:flex-none"
                            aria-label="Filter by aging"
                        >
                            <SelectValue placeholder="All aging">
                                {buckets.find(
                                    (bucket) =>
                                        bucket.value ===
                                        (currentBucket ?? 'all'),
                                )?.label ?? 'All aging'}
                            </SelectValue>
                        </SelectTrigger>

                        <SelectContent>
                            {buckets.map((bucket) => (
                                <SelectItem
                                    key={bucket.value}
                                    value={bucket.value}
                                >
                                    {bucket.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Toggle
                        asChild
                        variant="outline"
                        pressed={isMyQueue}
                        className="h-9 shrink-0 gap-2 px-3"
                    >
                        <Link
                            href={withFilters({
                                officer: isMyQueue ? null : auth.user.id,
                            })}
                            preserveScroll
                            prefetch
                        >
                            <UserRound />
                            My queue
                        </Link>
                    </Toggle>
                </div>

                <FilterTabs
                    tabs={[
                        {
                            value: 'all',
                            label: 'All overdue',
                            count: counts.all,
                            href: withFilters({ tab: 'all' }),
                        },
                        {
                            value: 'unassigned',
                            label: 'Unassigned',
                            count: counts.unassigned,
                            href: withFilters({ tab: 'unassigned' }),
                        },
                        {
                            value: 'promised',
                            label: 'Promised',
                            count: counts.promised,
                            href: withFilters({ tab: 'promised' }),
                        },
                        {
                            value: 'follow_up',
                            label: 'Follow-up due',
                            count: counts.follow_up,
                            href: withFilters({ tab: 'follow_up' }),
                        },
                    ]}
                    value={currentTab}
                    label="Filter the collections queue"
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

                            <Select
                                value={bulkOfficer || undefined}
                                onValueChange={setBulkOfficer}
                            >
                                <SelectTrigger
                                    className="w-[170px]"
                                    aria-label="Collection officer to apply"
                                >
                                    <SelectValue placeholder="Choose officer" />
                                </SelectTrigger>

                                <SelectContent>
                                    <SelectItem value="unassigned">
                                        Unassigned
                                    </SelectItem>

                                    {officers.map((officer) => (
                                        <SelectItem
                                            key={officer.value}
                                            value={String(officer.value)}
                                        >
                                            {officer.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>

                            <Button
                                size="sm"
                                onClick={submitBulkAssign}
                                disabled={bulkOfficer === ''}
                            >
                                <Users /> Assign
                            </Button>
                        </div>
                    </div>
                )}

                <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    {loans.data.length === 0 ? (
                        <EmptyState
                            icon={PhoneCall}
                            title="Nothing to collect"
                            description={
                                isMyQueue
                                    ? 'No overdue loans are assigned to you right now. Switch off My queue to see the whole overdue book.'
                                    : hasFilters
                                      ? 'No overdue loans match these filters. Try another officer, aging bucket or tab.'
                                      : 'No loan is overdue right now. New arrears appear here after the nightly overdue check.'
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
                                    <TableHead className="w-10">
                                        <Checkbox
                                            checked={
                                                allSelected
                                                    ? true
                                                    : someSelected
                                                      ? 'indeterminate'
                                                      : false
                                            }
                                            onCheckedChange={(checked) =>
                                                setSelected(
                                                    checked === true
                                                        ? selectableIds
                                                        : [],
                                                )
                                            }
                                            disabled={
                                                selectableIds.length === 0
                                            }
                                            aria-label="Select all loans on this page"
                                        />
                                    </TableHead>
                                    <TableHead>Loan</TableHead>
                                    <TableHead className="hidden md:table-cell">
                                        Officer
                                    </TableHead>
                                    <TableHead className="text-right">
                                        <div className="flex justify-end">
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
                                        </div>
                                    </TableHead>
                                    <TableHead className="hidden text-right lg:table-cell">
                                        <div className="flex justify-end">
                                            <SortableHeader
                                                label="Days late"
                                                column="days_past_due"
                                                currentSort={currentSort}
                                                direction={currentDirection}
                                                href={(direction) =>
                                                    withFilters({
                                                        sort: 'days_past_due',
                                                        direction,
                                                    })
                                                }
                                            />
                                        </div>
                                    </TableHead>
                                    <TableHead className="hidden text-right xl:table-cell">
                                        Outstanding
                                    </TableHead>
                                    <TableHead>Next step</TableHead>
                                    <TableHead className="hidden xl:table-cell">
                                        Last contact
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>

                            <TableBody>
                                {loans.data.map((loan) => (
                                    <TableRow key={loan.id}>
                                        <TableCell>
                                            {loan.can.assign && (
                                                <Checkbox
                                                    checked={selected.includes(
                                                        loan.id,
                                                    )}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        setSelected(
                                                            (current) =>
                                                                checked === true
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
                                                    aria-label={`Select loan ${loan.id}`}
                                                />
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <Link
                                                href={showLoan(loan.id)}
                                                className="font-medium whitespace-nowrap hover:underline"
                                                prefetch
                                            >
                                                Loan #{loan.id}
                                            </Link>

                                            <div className="text-sm">
                                                <Link
                                                    href={showBorrower(
                                                        loan.borrower_id,
                                                    )}
                                                    className="underline-offset-4 hover:underline"
                                                    prefetch
                                                >
                                                    {loan.borrower_name}
                                                </Link>
                                            </div>

                                            <div className="text-xs text-muted-foreground">
                                                {loan.borrower_phone} ·{' '}
                                                {loan.loan_product_name}
                                            </div>

                                            <div className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs md:hidden">
                                                <LoanStatusBadge
                                                    status={loan.status}
                                                />
                                                <span className="font-medium text-destructive">
                                                    {loan.days_past_due}d late
                                                </span>
                                            </div>

                                            <div className="mt-2 md:hidden">
                                                {officerSelect(loan)}
                                            </div>
                                        </TableCell>

                                        <TableCell className="hidden md:table-cell">
                                            {officerSelect(loan)}
                                        </TableCell>

                                        <TableCell className="text-right font-medium text-destructive tabular-nums">
                                            {formatMoney(loan.arrears)}
                                        </TableCell>

                                        <TableCell className="hidden text-right text-muted-foreground tabular-nums lg:table-cell">
                                            {loan.days_past_due}
                                        </TableCell>

                                        <TableCell className="hidden text-right tabular-nums xl:table-cell">
                                            {formatMoney(loan.outstanding)}
                                        </TableCell>

                                        <TableCell>
                                            {loan.promise ? (
                                                <div>
                                                    <span
                                                        className={cn(
                                                            'text-sm font-medium tabular-nums',
                                                            loan.promise
                                                                .missed &&
                                                                'text-destructive',
                                                        )}
                                                    >
                                                        {formatMoney(
                                                            loan.promise.amount,
                                                        )}
                                                    </span>

                                                    <div className="text-xs text-muted-foreground">
                                                        {loan.promise.missed
                                                            ? `Missed ${formatDateOnly(loan.promise.promised_at)}`
                                                            : `by ${formatDateOnly(loan.promise.promised_at)}`}
                                                        {!loan.promise.missed &&
                                                            loan.promise
                                                                .days_until !==
                                                                null &&
                                                            ` · ${loan.promise.days_until === 0 ? 'today' : `${loan.promise.days_until}d`}`}
                                                    </div>

                                                    {loan.next_follow_up && (
                                                        <div
                                                            className={cn(
                                                                'text-xs',
                                                                loan.follow_up_overdue
                                                                    ? 'text-destructive'
                                                                    : 'text-muted-foreground',
                                                            )}
                                                        >
                                                            {loan.follow_up_overdue
                                                                ? 'Follow up overdue'
                                                                : 'Follow up'}{' '}
                                                            {formatDateOnly(
                                                                loan.next_follow_up,
                                                            )}
                                                        </div>
                                                    )}
                                                </div>
                                            ) : loan.next_follow_up ? (
                                                <div>
                                                    <span className="text-sm">
                                                        {loan.follow_up_overdue
                                                            ? 'Follow up overdue'
                                                            : 'Follow up'}
                                                    </span>
                                                    <div
                                                        className={cn(
                                                            'text-xs',
                                                            loan.follow_up_overdue
                                                                ? 'text-destructive'
                                                                : 'text-muted-foreground',
                                                        )}
                                                    >
                                                        {formatDateOnly(
                                                            loan.next_follow_up,
                                                        )}
                                                    </div>
                                                </div>
                                            ) : (
                                                <span className="text-sm text-muted-foreground">
                                                    No promise
                                                </span>
                                            )}
                                        </TableCell>

                                        <TableCell className="hidden text-muted-foreground xl:table-cell">
                                            {loan.last_contact ? (
                                                <>
                                                    <div className="text-sm text-foreground">
                                                        {
                                                            loan.last_contact
                                                                .outcome_label
                                                        }
                                                    </div>
                                                    <div className="text-xs">
                                                        {
                                                            loan.last_contact
                                                                .channel_label
                                                        }{' '}
                                                        ·{' '}
                                                        {formatDateOnly(
                                                            loan.last_contact
                                                                .contacted_at,
                                                        )}
                                                    </div>
                                                </>
                                            ) : (
                                                'Never contacted'
                                            )}
                                        </TableCell>

                                        <TableCell>
                                            <div className="flex justify-end gap-2">
                                                {loan.can.log_contact && (
                                                    <Button
                                                        size="sm"
                                                        onClick={() =>
                                                            setContactLoan(loan)
                                                        }
                                                    >
                                                        <PhoneCall /> Log
                                                        contact
                                                    </Button>
                                                )}

                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    asChild
                                                >
                                                    <Link
                                                        href={showLoan(loan.id)}
                                                        prefetch
                                                    >
                                                        Open
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

                <DataPagination paginator={loans} />
            </div>

            <LogCollectionContactDialog
                loan={contactLoan ?? { id: 0, borrower_name: '' }}
                open={contactLoan !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setContactLoan(null);
                    }
                }}
                channels={collectionChannels}
                outcomes={collectionOutcomes}
                outstanding={contactLoan?.outstanding ?? '0'}
            />
        </>
    );
}

CollectionsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Administration',
            href: adminDashboard(),
        },
        {
            title: 'Collections',
            href: collectionsIndex(),
        },
    ],
};
