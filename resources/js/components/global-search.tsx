import { router, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    FileBarChart,
    Loader2,
    Search,
    User,
    Wallet,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    CommandDialog,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
    CommandSeparator,
} from '@/components/ui/command';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/format';
import { search as searchRoute } from '@/routes/admin';
import { index as borrowersIndex } from '@/routes/admin/borrowers';
import { index as loansIndex } from '@/routes/admin/loans';
import { index as reportsIndex } from '@/routes/admin/reports';
import type { GlobalSearchResults } from '@/types';

const EMPTY_RESULTS: GlobalSearchResults = {
    query: '',
    borrowers: [],
    loans: [],
};

/** Global borrower and loan search, opened with Ctrl/⌘ K. */
export function GlobalSearch() {
    const { auth } = usePage().props;
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<GlobalSearchResults>(EMPTY_RESULTS);
    const [loading, setLoading] = useState(false);
    const canSearch =
        auth.user?.role === 'admin' || auth.user?.role === 'super_admin';

    useEffect(() => {
        const handleKeyDown = (event: KeyboardEvent) => {
            if (
                (event.metaKey || event.ctrlKey) &&
                event.key.toLowerCase() === 'k'
            ) {
                event.preventDefault();
                setOpen((current) => !current);
            }
        };

        window.addEventListener('keydown', handleKeyDown);

        return () => window.removeEventListener('keydown', handleKeyDown);
    }, []);

    useEffect(() => {
        const trimmed = query.trim();

        if (!open || trimmed === '') {
            setResults(EMPTY_RESULTS);
            setLoading(false);

            return;
        }

        const controller = new AbortController();
        let cancelled = false;

        setLoading(true);

        const timeout = window.setTimeout(() => {
            fetch(searchRoute({ query: { q: trimmed } }).url, {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Search failed');
                    }

                    return response.json() as Promise<GlobalSearchResults>;
                })
                .then((payload) => {
                    if (!cancelled) {
                        setResults(payload);
                    }
                })
                .catch((error: unknown) => {
                    if (
                        !cancelled &&
                        !(
                            error instanceof DOMException &&
                            error.name === 'AbortError'
                        )
                    ) {
                        setResults(EMPTY_RESULTS);
                    }
                })
                .finally(() => {
                    if (!cancelled) {
                        setLoading(false);
                    }
                });
        }, 200);

        return () => {
            cancelled = true;
            window.clearTimeout(timeout);
            controller.abort();
        };
    }, [open, query]);

    const go = (url: string) => {
        setOpen(false);
        setQuery('');
        router.visit(url);
    };

    const handleOpenChange = (nextOpen: boolean) => {
        setOpen(nextOpen);

        if (!nextOpen) {
            setQuery('');
            setResults(EMPTY_RESULTS);
        }
    };

    const hasResults = results.borrowers.length > 0 || results.loans.length > 0;

    if (!canSearch) {
        return null;
    }

    return (
        <>
            <Button
                type="button"
                variant="outline"
                size="sm"
                aria-label="Search borrowers and loans"
                onClick={() => setOpen(true)}
                className="gap-2 font-normal text-muted-foreground"
            >
                <Search />
                <span className="hidden sm:inline">Search</span>
                <kbd className="hidden rounded border bg-muted px-1.5 py-0.5 font-mono text-[10px] md:inline">
                    Ctrl K
                </kbd>
            </Button>

            <CommandDialog
                open={open}
                onOpenChange={handleOpenChange}
                title="Search borrowers and loans"
                description="Find a borrower by name, phone or national ID, or open a loan by number."
                shouldFilter={false}
                contentClassName="sm:max-w-xl"
            >
                <div className="relative">
                    <CommandInput
                        value={query}
                        onValueChange={setQuery}
                        placeholder="Search borrowers and loans..."
                    />

                    {loading && (
                        <Loader2 className="absolute top-2.5 right-3 size-4 animate-spin text-muted-foreground" />
                    )}
                </div>

                <CommandList>
                    {!hasResults && (
                        <CommandEmpty>
                            {query.trim() === ''
                                ? 'Type a name, phone, national ID or loan number.'
                                : loading
                                  ? 'Searching...'
                                  : 'No borrowers or loans found.'}
                        </CommandEmpty>
                    )}

                    {query.trim() === '' && (
                        <>
                            <CommandGroup heading="Go to">
                                <CommandItem
                                    value="go-borrowers"
                                    onSelect={() => go(borrowersIndex().url)}
                                >
                                    <User /> Borrowers
                                    <ArrowRight className="ml-auto opacity-50" />
                                </CommandItem>
                                <CommandItem
                                    value="go-loans"
                                    onSelect={() => go(loansIndex().url)}
                                >
                                    <Wallet /> Loans
                                    <ArrowRight className="ml-auto opacity-50" />
                                </CommandItem>
                                <CommandItem
                                    value="go-reports"
                                    onSelect={() => go(reportsIndex().url)}
                                >
                                    <FileBarChart /> Reports
                                    <ArrowRight className="ml-auto opacity-50" />
                                </CommandItem>
                            </CommandGroup>
                            <CommandSeparator />
                        </>
                    )}

                    {results.borrowers.length > 0 && (
                        <CommandGroup heading="Borrowers">
                            {results.borrowers.map((borrower) => (
                                <CommandItem
                                    key={`borrower-${borrower.id}`}
                                    value={`borrower-${borrower.id}`}
                                    onSelect={() => go(borrower.url)}
                                >
                                    <User />
                                    <div className="flex min-w-0 flex-col">
                                        <span className="truncate">
                                            {borrower.name}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            {borrower.phone ?? 'No phone'}
                                        </span>
                                    </div>
                                    <ArrowRight className="ml-auto opacity-50" />
                                </CommandItem>
                            ))}
                        </CommandGroup>
                    )}

                    {results.loans.length > 0 && (
                        <CommandGroup heading="Loans">
                            {results.loans.map((loan) => (
                                <CommandItem
                                    key={`loan-${loan.id}`}
                                    value={`loan-${loan.id}`}
                                    onSelect={() => go(loan.url)}
                                >
                                    <Wallet />
                                    <div className="flex min-w-0 flex-col">
                                        <span className="truncate">
                                            Loan #{loan.id}
                                            {loan.borrower
                                                ? ` · ${loan.borrower}`
                                                : ''}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            {loan.status_label} ·{' '}
                                            {formatMoney(loan.principal)}
                                        </span>
                                    </div>
                                    <ArrowRight className="ml-auto opacity-50" />
                                </CommandItem>
                            ))}
                        </CommandGroup>
                    )}
                </CommandList>
            </CommandDialog>
        </>
    );
}
