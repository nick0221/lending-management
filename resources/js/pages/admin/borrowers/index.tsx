import { Head, Link } from '@inertiajs/react';
import { Pencil, Plus, Users } from 'lucide-react';
import { ConfirmDelete } from '@/components/confirm-delete';
import { DataPagination } from '@/components/data-pagination';
import { EmptyState } from '@/components/empty-state';
import { FilterTabs } from '@/components/filter-tabs';
import Heading from '@/components/heading';
import { KycBadge } from '@/components/kyc-badge';
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
}: {
    borrowers: Paginated<BorrowerListItem>;
    kycStatuses: KycStatusOption[];
    currentKycStatus: KycStatus | null;
    kycCounts: Record<string, number>;
}) {
    return (
        <>
            <Head title="Borrowers" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        title="Borrowers"
                        description="Register and manage the people your organisation lends to."
                    />

                    <Button asChild>
                        <Link href={createBorrower()}>
                            <Plus /> Add borrower
                        </Link>
                    </Button>
                </div>

                <FilterTabs
                    tabs={[
                        {
                            value: 'all',
                            label: 'All',
                            count: kycCounts.all,
                            href: borrowersIndex(),
                        },
                        ...kycStatuses.map((status) => ({
                            value: status.value,
                            label: status.label,
                            count: kycCounts[status.value] ?? 0,
                            href: borrowersIndex({
                                query: { kyc_status: status.value },
                            }),
                        })),
                    ]}
                    value={currentKycStatus ?? 'all'}
                    label="Filter borrowers by KYC status"
                    className="min-w-0"
                />

                <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    {borrowers.data.length === 0 ? (
                        <EmptyState
                            icon={Users}
                            title="No borrowers found"
                            description={
                                currentKycStatus
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
