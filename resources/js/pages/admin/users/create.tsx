import { Head } from '@inertiajs/react';
import { UserForm } from '@/components/admin/user-form';
import Heading from '@/components/heading';
import { dashboard as adminDashboard } from '@/routes/admin';
import {
    create as createUser,
    index as usersIndex,
    store,
} from '@/routes/admin/users';
import type { RoleOption } from '@/types';

export default function CreateUser({ roles }: { roles: RoleOption[] }) {
    return (
        <>
            <Head title="Create user" />

            <div className="mx-auto w-full max-w-2xl space-y-6 p-4">
                <Heading
                    title="Create user"
                    description="Add a new account and assign its access level."
                />

                <UserForm
                    action={store.form()}
                    roles={roles}
                    submitLabel="Create user"
                    passwordRequired
                />
            </div>
        </>
    );
}

CreateUser.layout = {
    breadcrumbs: [
        {
            title: 'Administration',
            href: adminDashboard(),
        },
        {
            title: 'Users',
            href: usersIndex(),
        },
        {
            title: 'Create user',
            href: createUser(),
        },
    ],
};
