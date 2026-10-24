import { Link, router, usePage } from "@inertiajs/react";
import { ChevronLeft, ChevronRight } from "lucide-react";
import {
    Pagination,
    PaginationContent,
    PaginationItem,
    PaginationLink,
} from "@/components/ui/pagination";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import type { Paginated } from "@/types";

const PAGE_SIZES = [15, 25, 50, 100];
const SMALLEST_PAGE_SIZE = Math.min(...PAGE_SIZES);

/**
 * Shared paginator for server-driven lists. Keeps the page number, range and
 * navigation in one place so every list behaves the same.
 */
export function DataPagination<T>({ paginator }: { paginator: Paginated<T> }) {
    const page = usePage();

    if (paginator.total <= SMALLEST_PAGE_SIZE) {
        return null;
    }

    const previous = paginator.links.find((link) =>
        link.label.includes("Previous"),
    );
    const next = paginator.links.find((link) => link.label.includes("Next"));
    const pageSizes = PAGE_SIZES.includes(paginator.per_page)
        ? PAGE_SIZES
        : [...PAGE_SIZES, paginator.per_page].sort(
              (first, second) => first - second,
          );

    const changePageSize = (value: string) => {
        const url = new URL(page.url, window.location.origin);

        url.searchParams.set("per_page", value);
        url.searchParams.delete("page");

        router.get(
            url.pathname + url.search,
            {},
            {
                preserveScroll: true,
                preserveState: true,
            },
        );
    };

    return (
        <div className="flex flex-col items-center justify-between gap-3 sm:flex-row">
            <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
                <p className="text-sm text-muted-foreground">
                    Showing {paginator.from ?? 0}–{paginator.to ?? 0} of{" "}
                    {paginator.total}
                </p>

                <div className="flex items-center gap-2">
                    <span className="text-sm text-muted-foreground">Rows</span>

                    <Select
                        value={String(paginator.per_page)}
                        onValueChange={changePageSize}
                    >
                        <SelectTrigger
                            className="h-8 w-[4.75rem]"
                            aria-label="Rows per page"
                        >
                            <SelectValue />
                        </SelectTrigger>

                        <SelectContent align="start" position="popper">
                            {pageSizes.map((size) => (
                                <SelectItem key={size} value={String(size)}>
                                    {size}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            </div>

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
                            Page {paginator.current_page} of{" "}
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
