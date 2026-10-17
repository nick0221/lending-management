import { Head } from '@inertiajs/react';
import { BorrowerForm } from '@/components/admin/borrower-form';
import Heading from '@/components/heading';
import { dashboard as adminDashboard } from '@/routes/admin';
import {
    edit as editBorrower,
    index as borrowersIndex,
    update,
} from '@/routes/admin/borrowers';
import type { Borrower, KycStatusOption, UserOption } from '@/types';

export default function EditBorrower({
    borrower,
    kycStatuses,
    users,
}: {
    borrower: Borrower;
    kycStatuses: KycStatusOption[];
    users: UserOption[];
}) {
    return (
        <>
            <Head title={`Edit ${borrower.name}`} />

            <div className="mx-auto w-full max-w-2xl space-y-6 p-4">
                <Heading
                    title="Edit borrower"
                    description={`Update ${borrower.name}'s details and KYC status.`}
                />

                <BorrowerForm
                    action={update.form(borrower.id)}
                    kycStatuses={kycStatuses}
                    users={users}
                    borrower={borrower}
                    submitLabel="Save changes"
                />
            </div>
        </>
    );
}

EditBorrower.layout = (props: { borrower: Borrower }) => ({
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
            title: 'Edit borrower',
            href: editBorrower(props.borrower.id),
        },
    ],
});
