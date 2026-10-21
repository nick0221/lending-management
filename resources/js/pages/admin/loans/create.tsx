import { Head } from '@inertiajs/react';
import { LoanForm } from '@/components/admin/loan-form';
import { PageHeader } from '@/components/page-header';
import { store } from '@/routes/admin/loans';
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

            <div className="mx-auto w-full max-w-6xl space-y-6 p-4">
                <PageHeader
                    title="New loan"
                    description="Originate a loan from one of your active products. The indicative schedule updates as you enter the terms."
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
