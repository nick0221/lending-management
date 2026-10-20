import { Head, Link } from '@inertiajs/react';
import { Pencil, Plus, Users } from 'lucide-react';
import { ConfirmDelete } from '@/components/confirm-delete';
import { DataPagination } from '@/components/data-pagination';
import { EmptyState } from '@/components/empty-state';
import { FilterTabs } from '@/components/filter-tabs';
import { KycBadge } from '@/components/kyc-badge';
import { PageHeader } from '@/components/page-header';
import { SearchInput } from '@/components/search-input';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { dashboard as adminDashboard } from '@/routes/admin';
import {
    create as createBorrower,
    destroy as destroyBorrower,
    edit as editBorrower,
    index as borrowersIndex,
} from '@/routes/admin/borrowers';
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
    const withFilters = (kycStatus?: KycStatus) =>
        borrowersIndex({
            query: {
                ...(kycStatus ? { kyc_status: kycStatus } : {}),
                ...(search ? { search } : {}),
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
                        <Button asChild>
                            <Link href={createBorrower()}>
                                <Plus /> Add borrower
                            </Link>
                        </Button>
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
                                      : 'Register the first borrower to start originating loans.'
                            }
                            action={
                                <Button asChild size="sm">
                                    <Link href={createBorrower()}>
                                        <Plus /> Add borrower
                                    </Link>
                                </Button>
                            }
                        />
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Phone</TableHead>
                                    <TableHead>National ID</TableHead>
                                    <TableHead>KYC</TableHead>
                                    <TableHead className="text-right">
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>

                            <TableBody>
                                {borrowers.data.map((borrower) => (
                                    <TableRow key={borrower.id}>
                                        <TableCell className="font-medium">
                                            <div>{borrower.name}</div>
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
                                        <TableCell>
                                            <div className="flex items-center justify-end gap-2">
                                                {borrower.can.update && (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={editBorrower(
                                                                borrower.id,
                                                            )}
                                                            prefetch
                                                        >
                                                            <Pencil />
                                                            Edit
                                                        </Link>
                                                    </Button>
                                                )}

                                                {borrower.can.delete && (
                                                    <ConfirmDelete
                                                        form={destroyBorrower.form(
                                                            borrower.id,
                                                        )}
                                                        title={`Delete ${borrower.name}?`}
                                                        description="This permanently deletes the borrower and every loan, payment and schedule attached to them. This cannot be undone."
                                                        testId="delete-borrower-button"
                                                    />
                                                )}
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
