import { Form, Head, Link } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import Heading from '@/components/heading';
import { RoleBadge } from '@/components/role-badge';
import { Button } from '@/components/ui/button';
import { dashboard as adminDashboard } from '@/routes/admin';
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
    const previous = users.links.find((link) => link.label.includes('Previous'));
    const next = users.links.find((link) => link.label.includes('Next'));

    return (
        <>
            <Head title="Users" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        title="Users"
                        description="Manage accounts and their access levels."
                    />

                    <Button asChild>
                        <Link href={createUser()} prefetch>
                            <Plus />
                            New user
                        </Link>
                    </Button>
                </div>

                <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-xs text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Name
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Email
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Role
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Actions
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                {users.data.map((user) => (
                                    <tr
                                        key={user.id}
                                        className="border-t border-sidebar-border/70 dark:border-sidebar-border"
                                    >
                                        <td className="px-4 py-3 font-medium">
                                            {user.name}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {user.email}
                                        </td>
                                        <td className="px-4 py-3">
                                            <RoleBadge role={user.role} />
                                        </td>
                                        <td className="px-4 py-3">
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
                                                    <Form
                                                        {...destroyUser.form(
                                                            user.id,
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
                                                                data-test="delete-user-button"
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

                                {users.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={4}
                                            className="px-4 py-8 text-center text-muted-foreground"
                                        >
                                            No users found.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {users.last_page > 1 && (
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
                            Page {users.current_page} of {users.last_page}
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
