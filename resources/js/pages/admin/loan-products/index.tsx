import { Form, Head, Link } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
}: {
    loanProducts: Paginated<LoanProductListItem>;
}) {
    const previous = loanProducts.links.find((link) =>
        link.label.includes('Previous'),
    );
    const next = loanProducts.links.find((link) => link.label.includes('Next'));

    return (
        <>
            <Head title="Loan products" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        title="Loan products"
                        description="Define the products, rates, and terms you offer."
                    />

                    <Button asChild>
                        <Link href={createLoanProduct()}>
                            <Plus /> Add product
                        </Link>
                    </Button>
                </div>

                <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-xs text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Product
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Interest
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Frequency
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Amount
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Term
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Status
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Actions
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                {loanProducts.data.map((loanProduct) => (
                                    <tr
                                        key={loanProduct.id}
                                        className="border-t border-sidebar-border/70 dark:border-sidebar-border"
                                    >
                                        <td className="px-4 py-3 font-medium">
                                            {loanProduct.name}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {Number(
                                                loanProduct.interest_rate,
                                            ).toFixed(2)}
                                            %{' '}
                                            {
                                                loanProduct.interest_method_label
                                            }
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {
                                                loanProduct.repayment_frequency_label
                                            }
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {loanProduct.min_amount} –{' '}
                                            {loanProduct.max_amount}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {loanProduct.min_term_months}–
                                            {loanProduct.max_term_months} mo
                                        </td>
                                        <td className="px-4 py-3">
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
                                        </td>
                                        <td className="px-4 py-3">
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
                                                    <Form
                                                        {...destroyLoanProduct.form(
                                                            loanProduct.id,
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
                                                                data-test="delete-loan-product-button"
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

                                {loanProducts.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={7}
                                            className="px-4 py-8 text-center text-muted-foreground"
                                        >
                                            No loan products found.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {loanProducts.last_page > 1 && (
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
                            Page {loanProducts.current_page} of{' '}
                            {loanProducts.last_page}
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
