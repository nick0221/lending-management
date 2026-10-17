import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { KycStatus } from '@/types';

const kycDetails: Record<
    KycStatus,
    { label: string; variant: 'default' | 'secondary' | 'outline' | 'destructive' }
> = {
    pending: { label: 'Pending', variant: 'outline' },
    verified: { label: 'Verified', variant: 'default' },
    rejected: { label: 'Rejected', variant: 'destructive' },
};

export function KycBadge({
    status,
    className,
}: {
    status: KycStatus;
    className?: string;
}) {
    const { label, variant } = kycDetails[status];

    return (
        <Badge variant={variant} className={cn(className)}>
            {label}
        </Badge>
    );
}
