import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import {
    Pagination,
    PaginationContent,
    PaginationItem,
    PaginationLink,
} from '@/components/ui/pagination';
import type { Paginated } from '@/types';

/**
 * Shared paginator for server-driven lists. Keeps the page number, range and
 * navigation in one place so every list behaves the same.
 */
export function DataPagination<T>({ paginator }: { paginator: Paginated<T> }) {
    if (paginator.last_page <= 1) {
        return null;
    }

    const previous = paginator.links.find((link) =>
        link.label.includes('Previous'),
    );
    const next = paginator.links.find((link) => link.label.includes('Next'));

    return (
        <div className="flex flex-col items-center justify-between gap-3 sm:flex-row">
            <p className="text-sm text-muted-foreground">
                Showing {paginator.from ?? 0}–{paginator.to ?? 0} of{' '}
                {paginator.total}
            </p>

            <Pagination className="mx-0 w-auto justify-start">
                <PaginationContent>
                    <PaginationItem>
                        {previous?.url ? (
                            <PaginationLink
                                asChild
                                size="sm"
                                aria-label="Previous page"
                            >
                                <Link href={previous.url} preserveScroll>
                                    <ChevronLeft />
                                    <span className="hidden sm:inline">
                                        Previous
                                    </span>
                                </Link>
                            </PaginationLink>
                        ) : (
                            <PaginationLink
                                size="sm"
                                aria-disabled="true"
                                tabIndex={-1}
                                className="pointer-events-none opacity-50"
                            >
                                <ChevronLeft />
                                <span className="hidden sm:inline">
                                    Previous
                                </span>
                            </PaginationLink>
                        )}
                    </PaginationItem>

                    <PaginationItem>
                        <span className="px-2 text-sm whitespace-nowrap text-muted-foreground">
                            Page {paginator.current_page} of{' '}
                            {paginator.last_page}
                        </span>
                    </PaginationItem>

                    <PaginationItem>
                        {next?.url ? (
                            <PaginationLink
                                asChild
                                size="sm"
                                aria-label="Next page"
                            >
                                <Link href={next.url} preserveScroll>
                                    <span className="hidden sm:inline">
                                        Next
                                    </span>
                                    <ChevronRight />
                                </Link>
                            </PaginationLink>
                        ) : (
                            <PaginationLink
                                size="sm"
                                aria-disabled="true"
                                tabIndex={-1}
                                className="pointer-events-none opacity-50"
                            >
                                <span className="hidden sm:inline">Next</span>
                                <ChevronRight />
                            </PaginationLink>
                        )}
                    </PaginationItem>
                </PaginationContent>
            </Pagination>
        </div>
    );
}
