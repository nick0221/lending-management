import { router, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Banknote,
    Bell,
    FileBarChart,
    HandCoins,
    Landmark,
    LayoutGrid,
    Loader2,
    PhoneCall,
    Receipt,
    ScrollText,
    Search,
    Server,
    Settings,
    ShieldCheck,
    User,
    Users,
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
import { formatDateOnly, formatMoney } from '@/lib/format';
import {
    dashboard as adminDashboard,
    search as searchRoute,
} from '@/routes/admin';
import { index as activityIndex } from '@/routes/admin/activity';
import { index as borrowersIndex } from '@/routes/admin/borrowers';
import { index as collectionsIndex } from '@/routes/admin/collections';
import { index as disbursementsIndex } from '@/routes/admin/disbursements';
import { index as loanProductsIndex } from '@/routes/admin/loan-products';
import { index as loansIndex } from '@/routes/admin/loans';
import { index as notificationsIndex } from '@/routes/admin/notifications';
import { index as paymentsIndex } from '@/routes/admin/payments';
import { index as reportsIndex } from '@/routes/admin/reports';
import { edit as settingsEdit } from '@/routes/admin/settings';
import { index as systemIndex } from '@/routes/admin/system';
import { index as usersIndex } from '@/routes/admin/users';
import type { GlobalSearchResults } from '@/types';

const EMPTY_RESULTS: GlobalSearchResults = {
    query: '',
    borrowers: [],
    loans: [],
    payments: [],
    disbursements: [],
};

/** Global borrower, loan, payment and payout search, opened with Ctrl/⌘ K. */
export function GlobalSearch() {
    const { auth } = usePage().props;
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<GlobalSearchResults>(EMPTY_RESULTS);
    const [loading, setLoading] = useState(false);
    const canSearch =
        auth.user?.role === 'admin' || auth.user?.role === 'super_admin';
    const isSuperAdmin = auth.user?.role === 'super_admin';

    const pages = [
        {
            value: 'go-overview',
            label: 'Overview',
            url: adminDashboard().url,
            icon: ShieldCheck,
        },
        {
            value: 'go-users',
            label: 'Users',
            url: usersIndex().url,
            icon: Users,
        },
        {
            value: 'go-borrowers',
            label: 'Borrowers',
            url: borrowersIndex().url,
            icon: HandCoins,
        },
        {
            value: 'go-loan-products',
            label: 'Loan products',
            url: loanProductsIndex().url,
            icon: Landmark,
        },
        {
            value: 'go-loans',
            label: 'Loans',
            url: loansIndex().url,
            icon: Wallet,
        },
        {
            value: 'go-payments',
            label: 'Payments',
            url: paymentsIndex().url,
            icon: Receipt,
        },
        {
            value: 'go-disbursements',
            label: 'Disbursements',
            url: disbursementsIndex().url,
            icon: Banknote,
        },
        {
            value: 'go-notifications',
            label: 'Notifications',
            url: notificationsIndex().url,
            icon: Bell,
        },
        {
            value: 'go-collections',
            label: 'Collections',
            url: collectionsIndex().url,
            icon: PhoneCall,
        },
        {
            value: 'go-reports',
            label: 'Reports',
            url: reportsIndex().url,
            icon: FileBarChart,
        },
        ...(isSuperAdmin
            ? [
                  {
                      value: 'go-activity',
                      label: 'Activity log',
                      url: activityIndex().url,
                      icon: ScrollText,
                  },
                  {
                      value: 'go-system',
                      label: 'System status',
                      url: systemIndex().url,
                      icon: Server,
                  },
                  {
                      value: 'go-settings',
                      label: 'Settings',
                      url: settingsEdit().url,
                      icon: Settings,
                  },
              ]
            : []),
    ];

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

    const hasResults =
        results.borrowers.length > 0 ||
        results.loans.length > 0 ||
        results.payments.length > 0 ||
        results.disbursements.length > 0;

    if (!canSearch) {
        return null;
    }

    return (
        <>
            <Button
                type="button"
                variant="outline"
                size="sm"
                aria-label="Search borrowers, loans, payments and payouts"
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
                title="Search borrowers, loans, payments and payouts"
                description="Find a borrower by name, phone or national ID, open a loan by number, or find a payment or payout by reference or number."
                shouldFilter={false}
                contentClassName="sm:max-w-xl"
            >
                <div className="relative">
                    <CommandInput
                        value={query}
                        onValueChange={setQuery}
                        placeholder="Search borrowers, loans, payments and payouts..."
                    />

                    {loading && (
                        <Loader2 className="absolute top-2.5 right-3 size-4 animate-spin text-muted-foreground" />
                    )}
                </div>

                <CommandList>
                    {!hasResults && (
                        <CommandEmpty>
                            {query.trim() === ''
                                ? 'Type a name, phone, national ID, loan number, payment or payout reference.'
                                : loading
                                  ? 'Searching...'
                                  : 'No borrowers, loans, payments or payouts found.'}
                        </CommandEmpty>
                    )}

                    {query.trim() === '' && (
                        <>
                            <CommandGroup heading="Go to">
                                {pages.map((page) => (
                                    <CommandItem
                                        key={page.value}
                                        value={page.value}
                                        onSelect={() => go(page.url)}
                                    >
                                        <page.icon /> {page.label}
                                        <ArrowRight className="ml-auto opacity-50" />
                                    </CommandItem>
                                ))}
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

                    {results.payments.length > 0 && (
                        <CommandGroup heading="Payments">
                            {results.payments.map((payment) => (
                                <CommandItem
                                    key={`payment-${payment.id}`}
                                    value={`payment-${payment.id}`}
                                    onSelect={() => go(payment.url)}
                                >
                                    <Receipt />
                                    <div className="flex min-w-0 flex-col">
                                        <span className="truncate">
                                            {payment.reference ||
                                                `Payment #${payment.id}`}
                                            {payment.borrower
                                                ? ` · ${payment.borrower}`
                                                : ''}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            {payment.method_label} ·{' '}
                                            {formatDateOnly(
                                                payment.payment_date,
                                            )}{' '}
                                            · {formatMoney(payment.amount)}
                                            {payment.reversed
                                                ? ' · Reversed'
                                                : ''}
                                        </span>
                                    </div>
                                    <ArrowRight className="ml-auto opacity-50" />
                                </CommandItem>
                            ))}
                        </CommandGroup>
                    )}
                    {results.disbursements.length > 0 && (
                        <CommandGroup heading="Disbursements">
                            {results.disbursements.map((disbursement) => (
                                <CommandItem
                                    key={`disbursement-${disbursement.id}`}
                                    value={`disbursement-${disbursement.id}`}
                                    onSelect={() => go(disbursement.url)}
                                >
                                    <Banknote />
                                    <div className="flex min-w-0 flex-col">
                                        <span className="truncate">
                                            {disbursement.reference ||
                                                `Payout #${disbursement.id}`}
                                            {disbursement.borrower
                                                ? ` · ${disbursement.borrower}`
                                                : ''}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            {disbursement.method_label} ·{' '}
                                            {formatDateOnly(
                                                disbursement.disbursed_at,
                                            )}{' '}
                                            · {formatMoney(disbursement.amount)}
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
