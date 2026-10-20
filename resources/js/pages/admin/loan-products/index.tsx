import { Head, Link } from '@inertiajs/react';
import { Landmark, Pencil, Plus } from 'lucide-react';
import { ConfirmDelete } from '@/components/confirm-delete';
import { DataPagination } from '@/components/data-pagination';
import { EmptyState } from '@/components/empty-state';
import { FilterTabs } from '@/components/filter-tabs';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
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
    create as createLoanProduct,
    destroy as destroyLoanProduct,
    edit as editLoanProduct,
    index as loanProductsIndex,
} from '@/routes/admin/loan-products';
import type { LoanProductListItem, Paginated } from '@/types';

export default function LoanProductsIndex({
    loanProducts,
    currentState,
    counts,
}: {
    loanProducts: Paginated<LoanProductListItem>;
    currentState: 'active' | 'inactive' | null;
    counts: Record<string, number>;
}) {
    return (
        <>
            <Head title="Loan products" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Loan products"
                    description="Define the products, rates, and terms you offer."
                    actions={
                        <Button asChild>
                            <Link href={createLoanProduct()}>
                                <Plus /> Add product
                            </Link>
                        </Button>
                    }
                />

                <FilterTabs
                    tabs={[
                        {
                            value: 'all',
                            label: 'All',
                            count: counts.all,
                            href: loanProductsIndex(),
                        },
                        {
                            value: 'active',
                            label: 'Active',
                            count: counts.active,
                            href: loanProductsIndex({
                                query: { state: 'active' },
                            }),
                        },
                        {
                            value: 'inactive',
                            label: 'Inactive',
                            count: counts.inactive,
                            href: loanProductsIndex({
                                query: { state: 'inactive' },
                            }),
                        },
                    ]}
                    value={currentState ?? 'all'}
                    label="Filter loan products by status"
                    className="min-w-0"
                />

                <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    {loanProducts.data.length === 0 ? (
                        <EmptyState
                            icon={Landmark}
                            title="No loan products found"
                            description={
                                currentState === 'active'
                                    ? 'No active loan products. Activate a product to offer it again.'
                                    : currentState === 'inactive'
                                      ? 'No inactive loan products. Every product is currently active.'
                                      : 'Create a product to define the interest, fees and terms you offer.'
                            }
                            action={
                                <Button asChild size="sm">
                                    <Link href={createLoanProduct()}>
                                        <Plus /> Add product
                                    </Link>
                                </Button>
                            }
                        />
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Product</TableHead>
                                    <TableHead>Interest</TableHead>
                                    <TableHead>Frequency</TableHead>
                                    <TableHead>Amount</TableHead>
                                    <TableHead>Term</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Loans</TableHead>
                                    <TableHead className="text-right">
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>

                            <TableBody>
                                {loanProducts.data.map((loanProduct) => (
                                    <TableRow key={loanProduct.id}>
                                        <TableCell className="font-medium">
                                            {loanProduct.name}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {Number(
                                                loanProduct.interest_rate,
                                            ).toFixed(2)}
                                            %{' '}
                                            {loanProduct.interest_method_label}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {
                                                loanProduct.repayment_frequency_label
                                            }
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {loanProduct.min_amount} –{' '}
                                            {loanProduct.max_amount}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {loanProduct.min_term_months}–
                                            {loanProduct.max_term_months} mo
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={
                                                    loanProduct.is_active
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {loanProduct.is_active
                                                    ? 'Active'
                                                    : 'Inactive'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground tabular-nums">
                                            {loanProduct.loans_count}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center justify-end gap-2">
                                                {loanProduct.can.update && (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={editLoanProduct(
                                                                loanProduct.id,
                                                            )}
                                                            prefetch
                                                        >
                                                            <Pencil />
                                                            Edit
                                                        </Link>
                                                    </Button>
                                                )}

                                                {loanProduct.can.delete && (
                                                    <ConfirmDelete
                                                        form={destroyLoanProduct.form(
                                                            loanProduct.id,
                                                        )}
                                                        title={`Delete ${loanProduct.name}?`}
                                                        description="This permanently deletes the product and every loan originated from it, including payments and schedules. Deactivate it instead if you only want to stop offering it."
                                                        testId="delete-loan-product-button"
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

                <DataPagination paginator={loanProducts} />
            </div>
        </>
    );
}

LoanProductsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Administration',
            href: adminDashboard(),
        },
        {
            title: 'Loan products',
            href: loanProductsIndex(),
        },
    ],
};
