import { Head, Link, router } from '@inertiajs/react';
import {
    BadgeCheck,
    Download,
    Plus,
    RotateCcw,
    TriangleAlert,
    Upload,
    Users,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { BorrowerRowActions } from '@/components/admin/borrower-row-actions';
import { DataPagination } from '@/components/data-pagination';
import { EmptyState } from '@/components/empty-state';
import { FilterTabs } from '@/components/filter-tabs';
import { KycBadge } from '@/components/kyc-badge';
import { PageHeader } from '@/components/page-header';
import { SearchInput } from '@/components/search-input';
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
import { formatMoney } from '@/lib/format';
import { dashboard as adminDashboard } from '@/routes/admin';
import {
    create as createBorrower,
    exportMethod as exportBorrowers,
    index as borrowersIndex,
    kycStatus as updateBorrowerKyc,
    show as showBorrower,
} from '@/routes/admin/borrowers';
import { create as importBorrowers } from '@/routes/admin/borrowers/imports';
import type {
    BorrowerListItem,
    KycStatus,
    KycStatusOption,
    Paginated,
} from '@/types';

export default function BorrowersIndex({
    borrowers,
    kycStatuses,
    currentKycStatus,
    arrearsOnly,
    kycCounts,
    search,
}: {
    borrowers: Paginated<BorrowerListItem>;
    kycStatuses: KycStatusOption[];
    currentKycStatus: KycStatus | null;
    arrearsOnly: boolean;
    kycCounts: Record<string, number>;
    search: string;
}) {
    const [selected, setSelected] = useState<number[]>([]);
    const [bulkStatus, setBulkStatus] = useState<KycStatus>('verified');
    const [updating, setUpdating] = useState(false);

    useEffect(() => {
        setSelected((current) =>
            current.filter((id) =>
                borrowers.data.some((borrower) => borrower.id === id),
            ),
        );
    }, [borrowers]);

    const pageIds = borrowers.data.map((borrower) => borrower.id);
    const allSelected =
        pageIds.length > 0 && pageIds.every((id) => selected.includes(id));
    const someSelected = selected.length > 0 && !allSelected;

    const submitBulkKyc = () => {
        if (selected.length === 0) {
            return;
        }

        router.patch(
            updateBorrowerKyc().url,
            {
                borrowers: selected,
                kyc_status: bulkStatus,
            },
            {
                preserveScroll: true,
                onStart: () => setUpdating(true),
                onFinish: () => setUpdating(false),
                onSuccess: () => setSelected([]),
            },
        );
    };

    const withFilters = (overrides: {
        kycStatus?: KycStatus | null;
        arrears?: boolean;
    }) => {
        const kycStatus =
            overrides.kycStatus === undefined
                ? currentKycStatus
                : overrides.kycStatus;
        const arrears = overrides.arrears ?? arrearsOnly;

        return borrowersIndex({
            query: {
                ...(kycStatus ? { kyc_status: kycStatus } : {}),
                ...(arrears ? { arrears: '1' } : {}),
                ...(search ? { search } : {}),
                ...(borrowers.per_page !== 15
                    ? { per_page: borrowers.per_page }
                    : {}),
            },
        });
    };

    const clearFilters = borrowersIndex({
        query:
            borrowers.per_page !== 15
                ? { per_page: borrowers.per_page }
                : undefined,
    });

    const exportHref = exportBorrowers({
        query: {
            ...(search ? { search } : {}),
            ...(currentKycStatus ? { kyc_status: currentKycStatus } : {}),
            ...(arrearsOnly ? { arrears: '1' } : {}),
        },
    });

    return (
        <>
            <Head title="Borrowers" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Borrowers"
                    description="Register and manage the people your organisation lends to."
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <Button variant="outline" asChild>
                                <a href={exportHref.url}>
                                    <Download /> Export CSV
                                </a>
                            </Button>

                            <Button variant="outline" asChild>
                                <Link href={importBorrowers()}>
                                    <Upload /> Import CSV
                                </Link>
                            </Button>

                            <Button asChild>
                                <Link href={createBorrower()}>
                                    <Plus /> Add borrower
                                </Link>
                            </Button>
                        </div>
                    }
                />

                <div className="flex flex-wrap items-center gap-3">
                    <SearchInput
                        value={search}
                        placeholder="Search name, email, phone or national ID"
                        label="Search borrowers"
                    />

                    <FilterTabs
                        tabs={[
                            {
                                value: 'all',
                                label: 'All',
                                count: kycCounts.all,
                                href: withFilters({ kycStatus: null }),
                            },
                            ...kycStatuses.map((status) => ({
                                value: status.value,
                                label: status.label,
                                count: kycCounts[status.value] ?? 0,
                                href: withFilters({ kycStatus: status.value }),
                            })),
                        ]}
                        value={currentKycStatus ?? 'all'}
                        label="Filter borrowers by KYC status"
                        className="min-w-0 flex-1 basis-full lg:basis-auto"
                    />

                    <Toggle
                        asChild
                        variant="outline"
                        pressed={arrearsOnly}
                        className="h-9 shrink-0 gap-2 px-3"
                    >
                        <Link
                            href={withFilters({ arrears: !arrearsOnly })}
                            preserveScroll
                            prefetch
                        >
                            <TriangleAlert />
                            In arrears
                        </Link>
                    </Toggle>
                </div>

                {selected.length > 0 && (
                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-primary/30 bg-primary/5 px-4 py-3">
                        <p className="text-sm font-medium">
                            {selected.length} borrower
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
                                value={bulkStatus}
                                onValueChange={(value) =>
                                    setBulkStatus(value as KycStatus)
                                }
                            >
                                <SelectTrigger
                                    className="w-[150px]"
                                    aria-label="KYC status to apply"
                                >
                                    <SelectValue>
                                        {
                                            kycStatuses.find(
                                                (status) =>
                                                    status.value === bulkStatus,
                                            )?.label
                                        }
                                    </SelectValue>
                                </SelectTrigger>

                                <SelectContent>
                                    {kycStatuses.map((status) => (
                                        <SelectItem
                                            key={status.value}
                                            value={status.value}
                                        >
                                            {status.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>

                            <Button
                                size="sm"
                                onClick={submitBulkKyc}
                                disabled={updating}
                            >
                                <BadgeCheck />
                                {updating ? 'Updating…' : 'Apply KYC'}
                            </Button>
                        </div>
                    </div>
                )}

                <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    {borrowers.data.length === 0 ? (
                        <EmptyState
                            icon={Users}
                            title="No borrowers found"
                            description={
                                search
                                    ? `No borrowers match "${search}". Try another term or clear the search.`
                                    : currentKycStatus
                                      ? 'No borrowers match this KYC status. Try another tab or register a new borrower.'
                                      : arrearsOnly
                                        ? 'No borrowers are in arrears right now. Turn off the arrears filter to see the whole register.'
                                        : 'Register the first borrower or import your existing book to start originating loans.'
                            }
                            action={
                                search || currentKycStatus || arrearsOnly ? (
                                    <Button asChild size="sm" variant="outline">
                                        <Link href={clearFilters}>
                                            <RotateCcw /> Clear filters
                                        </Link>
                                    </Button>
                                ) : (
                                    <div className="flex flex-wrap items-center justify-center gap-2">
                                        <Button asChild size="sm">
                                            <Link href={createBorrower()}>
                                                <Plus /> Add borrower
                                            </Link>
                                        </Button>

                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                        >
                                            <Link href={importBorrowers()}>
                                                <Upload /> Import CSV
                                            </Link>
                                        </Button>
                                    </div>
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
                                                        ? pageIds
                                                        : [],
                                                )
                                            }
                                            aria-label="Select all borrowers on this page"
                                        />
                                    </TableHead>
                                    <TableHead>Name</TableHead>
                                    <TableHead className="hidden xl:table-cell">
                                        Phone
                                    </TableHead>
                                    <TableHead className="hidden xl:table-cell">
                                        National ID
                                    </TableHead>
                                    <TableHead className="hidden sm:table-cell">
                                        KYC
                                    </TableHead>
                                    <TableHead className="hidden md:table-cell">
                                        Loans
                                    </TableHead>
                                    <TableHead className="hidden text-right md:table-cell">
                                        Outstanding
                                    </TableHead>
                                    <TableHead className="hidden text-right lg:table-cell">
                                        Arrears
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>

                            <TableBody>
                                {borrowers.data.map((borrower) => (
                                    <TableRow key={borrower.id}>
                                        <TableCell>
                                            <Checkbox
                                                checked={selected.includes(
                                                    borrower.id,
                                                )}
                                                onCheckedChange={(checked) =>
                                                    setSelected((current) =>
                                                        checked === true
                                                            ? [
                                                                  ...current,
                                                                  borrower.id,
                                                              ]
                                                            : current.filter(
                                                                  (id) =>
                                                                      id !==
                                                                      borrower.id,
                                                              ),
                                                    )
                                                }
                                                aria-label={`Select ${borrower.name}`}
                                            />
                                        </TableCell>
                                        <TableCell className="font-medium">
                                            <Link
                                                href={showBorrower(borrower.id)}
                                                className="hover:underline"
                                                prefetch
                                            >
                                                {borrower.name}
                                            </Link>
                                            {borrower.email && (
                                                <div className="hidden truncate text-xs text-muted-foreground sm:block">
                                                    {borrower.email}
                                                </div>
                                            )}
                                            <div className="text-xs text-muted-foreground xl:hidden">
                                                {borrower.phone}
                                            </div>
                                            {borrower.in_arrears && (
                                                <div className="text-xs font-medium text-destructive lg:hidden">
                                                    {formatMoney(
                                                        borrower.arrears,
                                                    )}{' '}
                                                    in arrears
                                                </div>
                                            )}
                                            <div className="mt-1 sm:hidden">
                                                <KycBadge
                                                    status={borrower.kyc_status}
                                                />
                                            </div>
                                        </TableCell>
                                        <TableCell className="hidden text-muted-foreground xl:table-cell">
                                            {borrower.phone}
                                        </TableCell>
                                        <TableCell className="hidden text-muted-foreground xl:table-cell">
                                            {borrower.national_id ?? '—'}
                                        </TableCell>
                                        <TableCell className="hidden sm:table-cell">
                                            <KycBadge
                                                status={borrower.kyc_status}
                                            />
                                        </TableCell>
                                        <TableCell className="hidden text-muted-foreground md:table-cell">
                                            {borrower.open_loan_count > 0 ? (
                                                <>
                                                    <span className="text-foreground tabular-nums">
                                                        {
                                                            borrower.open_loan_count
                                                        }
                                                    </span>{' '}
                                                    open
                                                </>
                                            ) : (
                                                'None open'
                                            )}
                                            {borrower.loan_count > 0 && (
                                                <div className="text-xs">
                                                    {borrower.loan_count} filed
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell className="hidden text-right tabular-nums md:table-cell">
                                            {borrower.open_loan_count > 0
                                                ? formatMoney(
                                                      borrower.outstanding,
                                                  )
                                                : '—'}
                                        </TableCell>
                                        <TableCell className="hidden text-right tabular-nums lg:table-cell">
                                            {borrower.in_arrears ? (
                                                <span className="font-medium text-destructive">
                                                    {formatMoney(
                                                        borrower.arrears,
                                                    )}
                                                </span>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    —
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center justify-end">
                                                <BorrowerRowActions
                                                    borrower={borrower}
                                                />
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </div>

                <DataPagination paginator={borrowers} />
            </div>
        </>
    );
}

BorrowersIndex.layout = {
    breadcrumbs: [
        {
            title: 'Administration',
            href: adminDashboard(),
        },
        {
            title: 'Borrowers',
            href: borrowersIndex(),
        },
    ],
};
