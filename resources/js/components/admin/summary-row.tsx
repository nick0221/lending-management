import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * A label/value pair for a form summary or compact detail list.
 */
export function SummaryRow({
    label,
    value,
    strong,
}: {
    label: string;
    value: ReactNode;
    strong?: boolean;
}) {
    return (
        <div className="flex items-start justify-between gap-3">
            <dt className="text-muted-foreground">{label}</dt>
            <dd
                className={cn(
                    'text-right font-medium break-words',
                    strong && 'text-base font-semibold',
                )}
            >
                {value}
            </dd>
        </div>
    );
}
