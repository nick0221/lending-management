import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { LoanStatus } from '@/types';

const statusDetails: Record<LoanStatus, { label: string; className: string }> =
    {
        draft: {
            label: 'Draft',
            className: 'border-border bg-muted text-muted-foreground',
        },
        pending: {
            label: 'Pending',
            className:
                'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300',
        },
        approved: {
            label: 'Approved',
            className:
                'border-sky-500/30 bg-sky-500/10 text-sky-700 dark:text-sky-300',
        },
        rejected: {
            label: 'Rejected',
            className:
                'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-300',
        },
        disbursed: {
            label: 'Disbursed',
            className:
                'border-indigo-500/30 bg-indigo-500/10 text-indigo-700 dark:text-indigo-300',
        },
        active: {
            label: 'Active',
            className:
                'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
        },
        closed: {
            label: 'Closed',
            className: 'border-border bg-muted text-muted-foreground',
        },
        defaulted: {
            label: 'Defaulted',
            className:
                'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-300',
        },
        written_off: {
            label: 'Written Off',
            className: 'border-border bg-muted text-muted-foreground',
        },
    };

export function LoanStatusBadge({
    status,
    className,
}: {
    status: LoanStatus;
    className?: string;
}) {
    const { label, className: statusClassName } = statusDetails[status];

    return (
        <Badge variant="outline" className={cn(statusClassName, className)}>
            {label}
        </Badge>
    );
}
