import { Head, Link } from '@inertiajs/react';
import { Check, Minus } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { RoleBadge } from '@/components/role-badge';
import { SectionCard } from '@/components/section-card';
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
import { index as usersIndex } from '@/routes/admin/users';
import type { RoleMatrixGroup, RoleOption } from '@/types';

function PermissionCell({ allowed }: { allowed: boolean }) {
    return (
        <span className="inline-flex items-center justify-center">
            {allowed ? (
                <Check
                    className="size-4 text-emerald-600 dark:text-emerald-400"
                    aria-hidden
                />
            ) : (
                <Minus className="size-4 text-muted-foreground" aria-hidden />
            )}
            <span className="sr-only">
                {allowed ? 'Allowed' : 'Not allowed'}
            </span>
        </span>
    );
}

export default function RoleMatrixPage({
    groups,
    roles,
    counts,
}: {
    groups: RoleMatrixGroup[];
    roles: RoleOption[];
    counts: Record<string, number>;
}) {
    return (
        <>
            <Head title="Role matrix" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Role matrix"
                    description="What each role can do. Every cell is evaluated from the application policies."
                />

                {groups.map((group) => (
                    <SectionCard
                        key={group.title}
                        title={group.title}
                        contentClassName="p-0"
                    >
                        <Table containerClassName="hidden md:block">
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="min-w-64">
                                        Ability
                                    </TableHead>

                                    {roles.map((role) => (
                                        <TableHead
                                            key={role.value}
                                            className="text-center"
                                        >
                                            <Link
                                                href={usersIndex({
                                                    query: {
                                                        role: role.value,
                                                    },
                                                })}
                                                prefetch
                                                className="inline-flex items-center gap-1.5 rounded-sm hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                            >
                                                <RoleBadge role={role.value} />
                                                <span className="text-xs font-normal text-muted-foreground tabular-nums">
                                                    {counts[role.value] ?? 0}
                                                </span>
                                            </Link>
                                        </TableHead>
                                    ))}
                                </TableRow>
                            </TableHeader>

                            <TableBody>
                                {group.abilities.map((ability) => (
                                    <TableRow key={ability.label}>
                                        <TableCell>
                                            <p className="font-medium">
                                                {ability.label}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {ability.description}
                                            </p>
                                        </TableCell>

                                        {roles.map((role) => (
                                            <TableCell
                                                key={role.value}
                                                className="text-center"
                                            >
                                                <PermissionCell
                                                    allowed={
                                                        ability.roles[
                                                            role.value
                                                        ]
                                                    }
                                                />
                                            </TableCell>
                                        ))}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>

                        <ul className="divide-y md:hidden">
                            {group.abilities.map((ability) => (
                                <li
                                    key={ability.label}
                                    className="space-y-3 p-4"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {ability.label}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {ability.description}
                                        </p>
                                    </div>

                                    <ul className="flex flex-wrap gap-2">
                                        {roles.map((role) => (
                                            <li
                                                key={role.value}
                                                className="inline-flex items-center gap-1.5 rounded-full border px-2 py-1 text-xs"
                                            >
                                                <PermissionCell
                                                    allowed={
                                                        ability.roles[
                                                            role.value
                                                        ]
                                                    }
                                                />
                                                <RoleBadge role={role.value} />
                                            </li>
                                        ))}
                                    </ul>
                                </li>
                            ))}
                        </ul>
                    </SectionCard>
                ))}

                <p className="text-xs text-muted-foreground">
                    The matrix is read-only. Access changes are made on the user
                    account, not here.
                </p>
            </div>
        </>
    );
}

RoleMatrixPage.layout = {
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
            title: 'Role matrix',
            href: rolesIndex(),
        },
    ],
};
