import { router } from '@inertiajs/react';
import { Search, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

/**
 * URL-backed, debounced search field. The active term lives in the query
 * string so links can share and preserve it; typing replaces the history
 * entry instead of adding one per keystroke.
 */
export function SearchInput({
    value,
    placeholder = 'Search…',
    param = 'search',
    label = 'Search',
    className,
}: {
    value: string;
    placeholder?: string;
    param?: string;
    label?: string;
    className?: string;
}) {
    const [term, setTerm] = useState(value);
    const isMounted = useRef(false);

    useEffect(() => {
        setTerm(value);
    }, [value]);

    useEffect(() => {
        if (!isMounted.current) {
            isMounted.current = true;

            return;
        }

        const timeout = window.setTimeout(() => {
            const url = new URL(window.location.href);
            const current = url.searchParams.get(param) ?? '';

            if (current === term) {
                return;
            }

            if (term === '') {
                url.searchParams.delete(param);
            } else {
                url.searchParams.set(param, term);
            }

            url.searchParams.delete('page');

            router.get(
                url.pathname + url.search,
                {},
                {
                    preserveScroll: true,
                    preserveState: true,
                    replace: true,
                },
            );
        }, 300);

        return () => window.clearTimeout(timeout);
    }, [term, param]);

    return (
        <div className={cn('relative w-full sm:max-w-xs', className)}>
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />

            <Input
                type="text"
                inputMode="search"
                value={term}
                onChange={(event) => setTerm(event.target.value)}
                placeholder={placeholder}
                aria-label={label}
                className="pr-9 pl-9"
            />

            {term !== '' && (
                <button
                    type="button"
                    onClick={() => setTerm('')}
                    aria-label="Clear search"
                    className="absolute top-1/2 right-2 -translate-y-1/2 rounded-sm p-1 text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    <X className="size-4" />
                </button>
            )}
        </div>
    );
}
