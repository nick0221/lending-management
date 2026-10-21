import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { KycStatus } from '@/types';

const kycDetails: Record<KycStatus, { label: string; className: string }> = {
    pending: {
        label: 'Pending',
        className:
            'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300',
    },
    verified: {
        label: 'Verified',
        className:
            'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    },
    rejected: {
        label: 'Rejected',
        className:
            'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-300',
    },
};

export function KycBadge({
    status,
    className,
}: {
    status: KycStatus;
    className?: string;
}) {
    const { label, className: statusClassName } = kycDetails[status];

    return (
        <Badge variant="outline" className={cn(statusClassName, className)}>
            {label}
        </Badge>
    );
}
