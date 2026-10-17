import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { Role } from '@/types';

const roleDetails: Record<
    Role,
    { label: string; variant: 'default' | 'secondary' | 'outline' }
> = {
    super_admin: { label: 'Super Admin', variant: 'default' },
    admin: { label: 'Admin', variant: 'secondary' },
    user: { label: 'User', variant: 'outline' },
};

export function RoleBadge({
    role,
    className,
}: {
    role: Role;
    className?: string;
}) {
    const { label, variant } = roleDetails[role];

    return (
        <Badge variant={variant} className={cn(className)}>
            {label}
        </Badge>
    );
}
