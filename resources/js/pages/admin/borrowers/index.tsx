import { Form, Head, Link } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import Heading from '@/components/heading';
import { KycBadge } from '@/components/kyc-badge';
import { Button } from '@/components/ui/button';
import { dashboard as adminDashboard } from '@/routes/admin';
import {
    create as createBorrower,
    destroy as destroyBorrower,
    edit as editBorrower,
    index as borrowersIndex,
} from '@/routes/admin/borrowers';
import type { BorrowerListItem, Paginated } from '@/types';

export default function BorrowersIndex({
    borrowers,
}: {
    borrowers: Paginated<BorrowerListItem>;
}) {
    const previous = borrowers.links.find((link) =>
        link.label.includes('Previous'),
    );
    const next = borrowers.links.find((link) => link.label.includes('Next'));

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

                <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-xs text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Name
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Phone
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        National ID
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        KYC
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Actions
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                {borrowers.data.map((borrower) => (
                                    <tr
                                        key={borrower.id}
                                        className="border-t border-sidebar-border/70 dark:border-sidebar-border"
                                    >
                                        <td className="px-4 py-3 font-medium">
                                            <div>{borrower.name}</div>
                                            {borrower.email && (
                                                <div className="text-xs text-muted-foreground">
                                                    {borrower.email}
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {borrower.phone}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {borrower.national_id ?? '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            <KycBadge
                                                status={borrower.kyc_status}
                                            />
                                        </td>
                                        <td className="px-4 py-3">
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
                                                    <Form
                                                        {...destroyBorrower.form(
                                                            borrower.id,
                                                        )}
                                                        options={{
                                                            preserveScroll: true,
                                                        }}
                                                    >
                                                        {({ processing }) => (
                                                            <Button
                                                                type="submit"
                                                                variant="destructive"
                                                                size="sm"
                                                                disabled={
                                                                    processing
                                                                }
                                                                data-test="delete-borrower-button"
                                                            >
                                                                <Trash2 />
                                                                Delete
                                                            </Button>
                                                        )}
                                                    </Form>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}

                                {borrowers.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            className="px-4 py-8 text-center text-muted-foreground"
                                        >
                                            No borrowers found.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {borrowers.last_page > 1 && (
                    <div className="flex items-center justify-between gap-4">
                        {previous?.url ? (
                            <Button variant="outline" size="sm" asChild>
                                <Link href={previous.url} preserveScroll>
                                    Previous
                                </Link>
                            </Button>
                        ) : (
                            <Button variant="outline" size="sm" disabled>
                                Previous
                            </Button>
                        )}

                        <span className="text-sm text-muted-foreground">
                            Page {borrowers.current_page} of{' '}
                            {borrowers.last_page}
                        </span>

                        {next?.url ? (
                            <Button variant="outline" size="sm" asChild>
                                <Link href={next.url} preserveScroll>
                                    Next
                                </Link>
                            </Button>
                        ) : (
                            <Button variant="outline" size="sm" disabled>
                                Next
                            </Button>
                        )}
                    </div>
                )}
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
