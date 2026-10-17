import { Head } from '@inertiajs/react';
import { LoanProductForm } from '@/components/admin/loan-product-form';
import Heading from '@/components/heading';
import { dashboard as adminDashboard } from '@/routes/admin';
import {
    edit as editLoanProduct,
    index as loanProductsIndex,
    update,
} from '@/routes/admin/loan-products';
import type {
    InterestMethodOption,
    LoanProduct,
    RepaymentFrequencyOption,
} from '@/types';

export default function EditLoanProduct({
    loanProduct,
    interestMethods,
    frequencies,
}: {
    loanProduct: LoanProduct;
    interestMethods: InterestMethodOption[];
    frequencies: RepaymentFrequencyOption[];
}) {
    return (
        <>
            <Head title={`Edit ${loanProduct.name}`} />

            <div className="mx-auto w-full max-w-2xl space-y-6 p-4">
                <Heading
                    title="Edit loan product"
                    description={`Update the terms for ${loanProduct.name}.`}
                />

                <LoanProductForm
                    action={update.form(loanProduct.id)}
                    interestMethods={interestMethods}
                    frequencies={frequencies}
                    loanProduct={loanProduct}
                    submitLabel="Save changes"
                />
            </div>
        </>
    );
}

EditLoanProduct.layout = (props: { loanProduct: LoanProduct }) => ({
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
            title: 'Edit product',
            href: editLoanProduct(props.loanProduct.id),
        },
    ],
});
