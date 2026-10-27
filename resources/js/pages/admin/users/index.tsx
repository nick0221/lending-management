import { Head, Link } from '@inertiajs/react';
import {
    BadgeCheck,
    Clock,
    Plus,
    RotateCcw,
    ShieldCheck,
    ShieldOff,
    Users,
} from 'lucide-react';
import { UserRowActions } from '@/components/admin/user-row-actions';
import { DataPagination } from '@/components/data-pagination';
import { EmptyState } from '@/components/empty-state';
import { FilterTabs } from '@/components/filter-tabs';
import { PageHeader } from '@/components/page-header';
import { RoleBadge } from '@/components/role-badge';
import { SearchInput } from '@/components/search-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate } from '@/lib/format';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as rolesIndex } from '@/routes/admin/roles';
import {
    create as createUser,
    edit as editUser,
    index as usersIndex,
} from '@/routes/admin/users';
import type { AdminUserListItem, Paginated, Role, RoleOption } from '@/types';

export default function UsersIndex({
    users,
    roles,
    currentRole,
    search,
    roleCounts,
}: {
    users: Paginated<AdminUserListItem>;
    roles: RoleOption[];
    currentRole: Role | null;
    search: string;
    roleCounts: Record<string, number>;
}) {
    const withFilters = (role?: Role) =>
        usersIndex({
            query: {
                ...(role ? { role } : {}),
                ...(search ? { search } : {}),
                ...(users.per_page !== 15 ? { per_page: users.per_page } : {}),
            },
        });

    const clearFilters = usersIndex({
        query: users.per_page !== 15 ? { per_page: users.per_page } : undefined,
    });

    const isFiltered = search !== '' || currentRole !== null;

    return (
        <>
            <Head title="Users" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Users"
                    description="Manage accounts, access levels and two-factor status."
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

                <div className="flex flex-wrap items-center gap-3">
                    <SearchInput
                        value={search}
                        placeholder="Search name or email"
                        label="Search users"
                    />

                    <FilterTabs
                        tabs={[
                            {
                                value: 'all',
                                label: 'All',
                                count: roleCounts.all,
                                href: withFilters(),
                            },
                            ...roles.map((role) => ({
                                value: role.value,
                                label: role.label,
                                count: roleCounts[role.value] ?? 0,
                                href: withFilters(role.value),
                            })),
                        ]}
                        value={currentRole ?? 'all'}
                        label="Filter users by role"
                        className="min-w-0 flex-1 basis-full lg:basis-auto"
                    />
                </div>

                <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    {users.data.length === 0 ? (
                        <EmptyState
                            icon={Users}
                            title={
                                isFiltered
                                    ? 'No matching users'
                                    : 'No users yet'
                            }
                            description={
                                isFiltered
                                    ? 'No accounts match the current search and role filter.'
                                    : 'Create the first account to give someone access to the admin panel.'
                            }
                            action={
                                isFiltered ? (
                                    <Button asChild size="sm" variant="outline">
                                        <Link href={clearFilters}>
                                            <RotateCcw /> Clear filters
                                        </Link>
                                    </Button>
                                ) : (
                                    <Button asChild size="sm">
                                        <Link href={createUser()}>
                                            <Plus /> New user
                                        </Link>
                                    </Button>
                                )
                            }
                        />
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Email</TableHead>
                                    <TableHead>Role</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>2FA</TableHead>
                                    <TableHead>Joined</TableHead>
                                    <TableHead className="text-right">
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>

                            <TableBody>
                                {users.data.map((user) => (
                                    <TableRow key={user.id}>
                                        <TableCell className="font-medium">
                                            {user.can.update ? (
                                                <Link
                                                    href={editUser(user.id)}
                                                    className="hover:underline"
                                                    prefetch
                                                >
                                                    {user.name}
                                                </Link>
                                            ) : (
                                                user.name
                                            )}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {user.email}
                                        </TableCell>
                                        <TableCell>
                                            <RoleBadge role={user.role} />
                                        </TableCell>
                                        <TableCell>
                                            {user.email_verified_at ? (
                                                <Badge
                                                    variant="outline"
                                                    className="border-emerald-600/30 text-emerald-700 dark:text-emerald-400"
                                                >
                                                    <BadgeCheck />
                                                    Verified
                                                </Badge>
                                            ) : (
                                                <Badge
                                                    variant="outline"
                                                    className="text-muted-foreground"
                                                >
                                                    <Clock />
                                                    Pending
                                                </Badge>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {user.two_factor_enabled ? (
                                                <Badge
                                                    variant="outline"
                                                    className="border-emerald-600/30 text-emerald-700 dark:text-emerald-400"
                                                >
                                                    <ShieldCheck />
                                                    On
                                                </Badge>
                                            ) : (
                                                <Badge
                                                    variant="outline"
                                                    className="text-muted-foreground"
                                                >
                                                    <ShieldOff />
                                                    Off
                                                </Badge>
                                            )}
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap text-muted-foreground">
                                            {formatDate(user.created_at)}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center justify-end">
                                                <UserRowActions user={user} />
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
