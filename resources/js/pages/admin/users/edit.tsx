import { Head } from '@inertiajs/react';
import { UserForm } from '@/components/admin/user-form';
import { PageHeader } from '@/components/page-header';
import { dashboard as adminDashboard } from '@/routes/admin';
import {
    edit as editUser,
    index as usersIndex,
    update,
} from '@/routes/admin/users';
import type { AdminUser, RoleOption } from '@/types';

export default function EditUser({
    user,
    roles,
}: {
    user: AdminUser;
    roles: RoleOption[];
}) {
    return (
        <>
            <Head title={`Edit ${user.name}`} />

            <div className="mx-auto w-full max-w-6xl space-y-6 p-4">
                <PageHeader
                    title="Edit user"
                    description={`Update ${user.name}'s account and access level.`}
                />

                <UserForm
                    action={update.form(user.id)}
                    roles={roles}
                    user={user}
                    submitLabel="Save changes"
                />
            </div>
        </>
    );
}

EditUser.layout = (props: { user: AdminUser }) => ({
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
            title: 'Edit user',
            href: editUser(props.user.id),
        },
    ],
});
