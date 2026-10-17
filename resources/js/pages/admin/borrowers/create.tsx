import { Head } from '@inertiajs/react';
import { BorrowerForm } from '@/components/admin/borrower-form';
import Heading from '@/components/heading';
import { dashboard as adminDashboard } from '@/routes/admin';
import {
    create as createBorrower,
    index as borrowersIndex,
    store,
} from '@/routes/admin/borrowers';
import type { KycStatusOption, UserOption } from '@/types';

export default function CreateBorrower({
    kycStatuses,
    users,
}: {
    kycStatuses: KycStatusOption[];
    users: UserOption[];
}) {
    return (
        <>
            <Head title="Register borrower" />

            <div className="mx-auto w-full max-w-2xl space-y-6 p-4">
                <Heading
                    title="Register borrower"
                    description="Capture the borrower's details and KYC status."
                />

                <BorrowerForm
                    action={store.form()}
                    kycStatuses={kycStatuses}
                    users={users}
                    submitLabel="Register borrower"
                />
            </div>
        </>
    );
}

CreateBorrower.layout = {
    breadcrumbs: [
        {
            title: 'Administration',
            href: adminDashboard(),
        },
        {
            title: 'Borrowers',
            href: borrowersIndex(),
        },
        {
            title: 'Register borrower',
            href: createBorrower(),
        },
    ],
};
