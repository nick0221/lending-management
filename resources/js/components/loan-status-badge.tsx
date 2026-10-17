import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { LoanStatus } from '@/types';

const statusDetails: Record<
    LoanStatus,
    { label: string; variant: 'default' | 'secondary' | 'outline' | 'destructive' }
> = {
    draft: { label: 'Draft', variant: 'outline' },
    pending: { label: 'Pending', variant: 'outline' },
    approved: { label: 'Approved', variant: 'default' },
    rejected: { label: 'Rejected', variant: 'destructive' },
    disbursed: { label: 'Disbursed', variant: 'default' },
    active: { label: 'Active', variant: 'default' },
    closed: { label: 'Closed', variant: 'secondary' },
    defaulted: { label: 'Defaulted', variant: 'destructive' },
    written_off: { label: 'Written Off', variant: 'secondary' },
};

export function LoanStatusBadge({
    status,
    className,
}: {
    status: LoanStatus;
    className?: string;
}) {
    const { label, variant } = statusDetails[status];

    return (
        <Badge variant={variant} className={cn(className)}>
            {label}
        </Badge>
    );
}
