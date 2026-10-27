import { Head, Link } from '@inertiajs/react';
import { Landmark, Plus, RotateCcw } from 'lucide-react';
import { LoanProductCard } from '@/components/admin/loan-product-card';
import { DataPagination } from '@/components/data-pagination';
import { EmptyState } from '@/components/empty-state';
import { FilterTabs } from '@/components/filter-tabs';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { dashboard as adminDashboard } from '@/routes/admin';
import {
    create as createLoanProduct,
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
    const withState = (state?: 'active' | 'inactive') =>
        loanProductsIndex({
            query: {
                ...(state ? { state } : {}),
                ...(loanProducts.per_page !== 15
                    ? { per_page: loanProducts.per_page }
                    : {}),
            },
        });

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
                            href: withState(),
                        },
                        {
                            value: 'active',
                            label: 'Active',
                            count: counts.active,
                            href: withState('active'),
                        },
                        {
                            value: 'inactive',
                            label: 'Inactive',
                            count: counts.inactive,
                            href: withState('inactive'),
                        },
                    ]}
                    value={currentState ?? 'all'}
                    label="Filter loan products by status"
                    className="min-w-0"
                />

                {loanProducts.data.length === 0 ? (
                    <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
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
                                currentState ? (
                                    <Button asChild size="sm" variant="outline">
                                        <Link href={withState()}>
                                            <RotateCcw /> View all products
                                        </Link>
                                    </Button>
                                ) : (
                                    <Button asChild size="sm">
                                        <Link href={createLoanProduct()}>
                                            <Plus /> Add product
                                        </Link>
                                    </Button>
                                )
                            }
                        />
                    </div>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {loanProducts.data.map((loanProduct) => (
                            <LoanProductCard
                                key={loanProduct.id}
                                loanProduct={loanProduct}
                            />
                        ))}
                    </div>
                )}

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
