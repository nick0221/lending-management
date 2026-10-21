import { Head } from '@inertiajs/react';
import { LoanProductForm } from '@/components/admin/loan-product-form';
import { PageHeader } from '@/components/page-header';
import { dashboard as adminDashboard } from '@/routes/admin';
import {
    create as createLoanProduct,
    index as loanProductsIndex,
    store,
} from '@/routes/admin/loan-products';
import type { InterestMethodOption, RepaymentFrequencyOption } from '@/types';

export default function CreateLoanProduct({
    interestMethods,
    frequencies,
    defaults,
}: {
    interestMethods: InterestMethodOption[];
    frequencies: RepaymentFrequencyOption[];
    defaults: {
        penalty_rate: string;
        grace_period_days: string;
    };
}) {
    return (
        <>
            <Head title="Create loan product" />

            <div className="mx-auto w-full max-w-6xl space-y-6 p-4">
                <PageHeader
                    title="Create loan product"
                    description="Define the interest, amounts, and terms for this product."
                />

                <LoanProductForm
                    action={store.form()}
                    interestMethods={interestMethods}
                    frequencies={frequencies}
                    defaults={defaults}
                    submitLabel="Create product"
                />
            </div>
        </>
    );
}

CreateLoanProduct.layout = {
    breadcrumbs: [
        {
            title: 'Administration',
            href: adminDashboard(),
        },
        {
            title: 'Loan products',
            href: loanProductsIndex(),
        },
        {
            title: 'Create product',
            href: createLoanProduct(),
        },
    ],
};
