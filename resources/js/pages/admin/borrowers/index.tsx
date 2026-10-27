import { Head, Link, router } from '@inertiajs/react';
import { BadgeCheck, Plus, RotateCcw, Upload, Users } from 'lucide-react';
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
import { formatMoney } from '@/lib/format';
import { dashboard as adminDashboard } from '@/routes/admin';
import {
    create as createBorrower,
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
    kycCounts,
    search,
}: {
    borrowers: Paginated<BorrowerListItem>;
    kycStatuses: KycStatusOption[];
    currentKycStatus: KycStatus | null;
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

    const withFilters = (kycStatus?: KycStatus) =>
        borrowersIndex({
            query: {
                ...(kycStatus ? { kyc_status: kycStatus } : {}),
                ...(search ? { search } : {}),
                ...(borrowers.per_page !== 15
                    ? { per_page: borrowers.per_page }
                    : {}),
            },
        });

    const clearFilters = borrowersIndex({
        query:
            borrowers.per_page !== 15
                ? { per_page: borrowers.per_page }
                : undefined,
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
                        placeholder="Search name, email or phone"
                        label="Search borrowers"
                    />

                    <FilterTabs
                        tabs={[
                            {
                                value: 'all',
                                label: 'All',
                                count: kycCounts.all,
                                href: withFilters(),
                            },
                            ...kycStatuses.map((status) => ({
                                value: status.value,
                                label: status.label,
                                count: kycCounts[status.value] ?? 0,
                                href: withFilters(status.value),
                            })),
                        ]}
                        value={currentKycStatus ?? 'all'}
                        label="Filter borrowers by KYC status"
                        className="min-w-0 flex-1 basis-full lg:basis-auto"
                    />
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
                                      : 'Register the first borrower or import your existing book to start originating loans.'
                            }
                            action={
                                search || currentKycStatus ? (
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
                                    <TableHead>Phone</TableHead>
                                    <TableHead>National ID</TableHead>
                                    <TableHead>KYC</TableHead>
                                    <TableHead>Loans</TableHead>
                                    <TableHead className="text-right">
                                        Outstanding
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
                                                <div className="text-xs text-muted-foreground">
                                                    {borrower.email}
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {borrower.phone}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {borrower.national_id ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            <KycBadge
                                                status={borrower.kyc_status}
                                            />
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
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
                                        <TableCell className="text-right tabular-nums">
                                            {borrower.open_loan_count > 0
                                                ? formatMoney(
                                                      borrower.outstanding,
                                                  )
                                                : '—'}
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
