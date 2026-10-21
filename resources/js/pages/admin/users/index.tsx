import { Head, Link } from '@inertiajs/react';
import { Pencil, Plus, ShieldCheck, Users } from 'lucide-react';
import { ConfirmDelete } from '@/components/confirm-delete';
import { DataPagination } from '@/components/data-pagination';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { RoleBadge } from '@/components/role-badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as rolesIndex } from '@/routes/admin/roles';
import {
    create as createUser,
    destroy as destroyUser,
    edit as editUser,
    index as usersIndex,
} from '@/routes/admin/users';
import type { AdminUserListItem, Paginated } from '@/types';

export default function UsersIndex({
    users,
}: {
    users: Paginated<AdminUserListItem>;
}) {
    return (
        <>
            <Head title="Users" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Users"
                    description="Manage accounts and their access levels."
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" asChild>
                                <Link href={rolesIndex()} prefetch>
                                    <ShieldCheck />
                                    Role matrix
                                </Link>
                            </Button>

                            <Button asChild>
                                <Link href={createUser()} prefetch>
                                    <Plus />
                                    New user
                                </Link>
                            </Button>
                        </div>
                    }
                />

                <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    {users.data.length === 0 ? (
                        <EmptyState
                            icon={Users}
                            title="No users found"
                            description="Create the first account to give someone access to the admin panel."
                            action={
                                <Button asChild size="sm">
                                    <Link href={createUser()}>
                                        <Plus /> New user
                                    </Link>
                                </Button>
                            }
                        />
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Email</TableHead>
                                    <TableHead>Role</TableHead>
                                    <TableHead className="text-right">
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>

                            <TableBody>
                                {users.data.map((user) => (
                                    <TableRow key={user.id}>
                                        <TableCell className="font-medium">
                                            {user.name}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {user.email}
                                        </TableCell>
                                        <TableCell>
                                            <RoleBadge role={user.role} />
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center justify-end gap-2">
                                                {user.can.update && (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={editUser(
                                                                user.id,
                                                            )}
                                                            prefetch
                                                        >
                                                            <Pencil />
                                                            Edit
                                                        </Link>
                                                    </Button>
                                                )}

                                                {user.can.delete && (
                                                    <ConfirmDelete
                                                        form={destroyUser.form(
                                                            user.id,
                                                        )}
                                                        title={`Delete ${user.name}?`}
                                                        description="This permanently removes the account and revokes access. Any linked borrower profile and its loan history are kept."
                                                        testId="delete-user-button"
                                                    />
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </div>

                <DataPagination paginator={users} />
            </div>
        </>
    );
}

UsersIndex.layout = {
    breadcrumbs: [
        {
            title: 'Administration',
            href: adminDashboard(),
        },
        {
            title: 'Users',
            href: usersIndex(),
        },
    ],
};
