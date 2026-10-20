import { Link } from '@inertiajs/react';
import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react';
import type { ComponentProps } from 'react';

/**
 * Table header link that toggles between descending and ascending order for
 * one column while preserving the other filters.
 */
export function SortableHeader({
    label,
    column,
    currentSort,
    direction,
    href,
}: {
    label: string;
    column: string;
    currentSort: string | null;
    direction: 'asc' | 'desc';
    href: (direction: 'asc' | 'desc') => ComponentProps<typeof Link>['href'];
}) {
    const isActive = currentSort === column;
    const nextDirection = isActive && direction === 'desc' ? 'asc' : 'desc';
    const Icon = isActive
        ? direction === 'desc'
            ? ArrowDown
            : ArrowUp
        : ArrowUpDown;

    return (
        <Link
            href={href(nextDirection)}
            preserveScroll
            prefetch
            className="inline-flex items-center gap-1 hover:text-foreground"
            aria-label={`Sort by ${label} ${nextDirection === 'asc' ? 'ascending' : 'descending'}`}
        >
            {label}
            <Icon className={isActive ? 'size-3.5' : 'size-3.5 opacity-40'} />
        </Link>
    );
}
