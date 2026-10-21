import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { Role } from '@/types';

const roleDetails: Record<Role, { label: string; className: string }> = {
    super_admin: {
        label: 'Super Admin',
        className:
            'border-violet-500/30 bg-violet-500/10 text-violet-700 dark:text-violet-300',
    },
    admin: {
        label: 'Admin',
        className:
            'border-sky-500/30 bg-sky-500/10 text-sky-700 dark:text-sky-300',
    },
    user: {
        label: 'User',
        className: 'border-border bg-muted text-muted-foreground',
    },
};

export function RoleBadge({
    role,
    className,
}: {
    role: Role;
    className?: string;
}) {
    const { label, className: roleClassName } = roleDetails[role];

    return (
        <Badge variant="outline" className={cn(roleClassName, className)}>
            {label}
        </Badge>
    );
}
