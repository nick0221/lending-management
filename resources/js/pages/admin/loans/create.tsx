import { Head } from '@inertiajs/react';
import { LoanForm } from '@/components/admin/loan-form';
import Heading from '@/components/heading';
import { dashboard as adminDashboard } from '@/routes/admin';
import {
    create as createLoan,
    index as loansIndex,
    store,
} from '@/routes/admin/loans';
import type { BorrowerOption, LoanProductOption } from '@/types';

export default function CreateLoan({
    borrowers,
    loanProducts,
}: {
    borrowers: BorrowerOption[];
    loanProducts: LoanProductOption[];
}) {
    return (
        <>
            <Head title="New loan" />

            <div className="mx-auto w-full max-w-2xl space-y-6 p-4">
                <Heading
                    title="New loan"
                    description="Originate a loan from one of your active products."
                />

                <LoanForm
                    action={store.form()}
                    borrowers={borrowers}
                    loanProducts={loanProducts}
                    submitLabel="Create loan"
                />
            </div>
        </>
    );
}

CreateLoan.layout = {
    breadcrumbs: [
        {
            title: 'Administration',
            href: adminDashboard(),
        },
        {
            title: 'Loans',
            href: loansIndex(),
        },
        {
            title: 'New loan',
            href: createLoan(),
        },
    ],
};
