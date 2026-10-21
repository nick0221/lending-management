import { Head } from '@inertiajs/react';
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
}: {
    groups: RoleMatrixGroup[];
    roles: RoleOption[];
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
                        <Table>
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
                                            <RoleBadge role={role.value} />
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
