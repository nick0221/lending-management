import { Form, Head, Link } from '@inertiajs/react';
import { Eye, Plus, Trash2 } from 'lucide-react';
import Heading from '@/components/heading';
import { LoanStatusBadge } from '@/components/loan-status-badge';
import { Button } from '@/components/ui/button';
import { dashboard as adminDashboard } from '@/routes/admin';
import {
    create as createLoan,
    destroy as destroyLoan,
    index as loansIndex,
    show as showLoan,
} from '@/routes/admin/loans';
import type { LoanListItem, LoanStatusOption, Paginated } from '@/types';

export default function LoansIndex({
    loans,
    statuses,
    currentStatus,
    overdueOnly,
}: {
    loans: Paginated<LoanListItem>;
    statuses: LoanStatusOption[];
    currentStatus: string | null;
    overdueOnly: boolean;
}) {
    const previous = loans.links.find((link) =>
        link.label.includes('Previous'),
    );
    const next = loans.links.find((link) => link.label.includes('Next'));

    const filters: { value: string; label: string }[] = [
        { value: '', label: 'All' },
        ...statuses,
    ];

    const withFilters = (overrides: {
        status?: string | null;
        overdue?: boolean;
    }) => {
        const status = overrides.status ?? currentStatus ?? '';
        const overdue = overrides.overdue ?? overdueOnly;

        return loansIndex({
            query: {
                ...(status ? { status } : {}),
                ...(overdue ? { overdue: '1' } : {}),
            },
        });
    };

    return (
        <>
            <Head title="Loans" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        title="Loans"
                        description="Originate and review loan applications."
                    />

                    <Button asChild>
                        <Link href={createLoan()}>
                            <Plus /> New loan
                        </Link>
                    </Button>
                </div>

                <div className="flex flex-wrap gap-2">
                    {filters.map((filter) => (
                        <Button
                            key={filter.value || 'all'}
                            asChild
                            size="sm"
                            variant={
                                (currentStatus ?? '') === filter.value
                                    ? 'default'
                                    : 'outline'
                            }
                        >
                            <Link
                                href={withFilters({
                                    status: filter.value,
                                    overdue: false,
                                })}
                                preserveScroll
                            >
                                {filter.label}
                            </Link>
                        </Button>
                    ))}

                    <Button
                        asChild
                        size="sm"
                        variant={overdueOnly ? 'default' : 'outline'}
                    >
                        <Link
                            href={withFilters({ overdue: !overdueOnly })}
                            preserveScroll
                        >
                            Overdue only
                        </Link>
                    </Button>
                </div>

                <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-xs text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Borrower
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Product
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Principal
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Terms
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Status
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Arrears
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Applied
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Actions
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                {loans.data.map((loan) => (
                                    <tr
                                        key={loan.id}
                                        className="border-t border-sidebar-border/70 dark:border-sidebar-border"
                                    >
                                        <td className="px-4 py-3 font-medium">
                                            {loan.borrower_name}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {loan.loan_product_name}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {Number(loan.principal).toFixed(2)}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {loan.term_months} mo ·{' '}
                                            {
                                                loan.repayment_frequency_label
                                            }
                                        </td>
                                        <td className="px-4 py-3">
                                            <LoanStatusBadge
                                                status={loan.status}
                                            />
                                        </td>
                                        <td className="px-4 py-3">
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
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {loan.applied_at
                                                ? new Date(
                                                      loan.applied_at,
                                                  ).toLocaleDateString()
                                                : '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center justify-end gap-2">
                                                {loan.can.view && (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={showLoan(
                                                                loan.id,
                                                            )}
                                                            prefetch
                                                        >
                                                            <Eye />
                                                            View
                                                        </Link>
                                                    </Button>
                                                )}

                                                {loan.can.delete && (
                                                    <Form
                                                        {...destroyLoan.form(
                                                            loan.id,
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
                                                                data-test="delete-loan-button"
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

                                {loans.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={8}
                                            className="px-4 py-8 text-center text-muted-foreground"
                                        >
                                            No loans found.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {loans.last_page > 1 && (
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
                            Page {loans.current_page} of {loans.last_page}
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
