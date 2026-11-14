import { Head, Link, router } from '@inertiajs/react';
import {
    Bell,
    CircleAlert,
    CircleCheck,
    CircleSlash,
    Clock,
    Download,
    RotateCcw,
    Send,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { DataPagination } from '@/components/data-pagination';
import { DateRangePicker } from '@/components/date-range-picker';
import { EmptyState } from '@/components/empty-state';
import { FilterTabs } from '@/components/filter-tabs';
import { PageHeader } from '@/components/page-header';
import { SearchInput } from '@/components/search-input';
import StatCard from '@/components/stat-card';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard as adminDashboard } from '@/routes/admin';
import { show as showBorrower } from '@/routes/admin/borrowers';
import { show as showLoan } from '@/routes/admin/loans';
import { resend as resendNotification } from '@/routes/admin/loans/notifications';
import {
    exportMethod as exportNotifications,
    index as notificationsIndex,
    resend as resendNotificationsBulk,
} from '@/routes/admin/notifications';
import type {
    NotificationOption,
    NotificationRegisterRow,
    NotificationStatus,
    Paginated,
} from '@/types';

const statusVariant = (status: NotificationStatus) => {
    switch (status) {
        case 'sent':
            return 'default' as const;
        case 'failed':
            return 'destructive' as const;
        case 'skipped':
            return 'secondary' as const;
        default:
            return 'outline' as const;
    }
};

/**
 * Only failed or skipped deliveries can be queued again in bulk.
 */
function isBulkResendable(log: NotificationRegisterRow): boolean {
    return (
        log.can_resend && (log.status === 'failed' || log.status === 'skipped')
    );
}

/**
 * Every email and SMS delivery recorded against the book, so staff can see
 * what went out, what failed and re-send from one place.
 */
export default function NotificationsIndex({
    logs,
    counts,
    channels,
    statuses,
    types,
    currentStatus,
    currentChannel,
    currentType,
    currentBorrower,
    currentLoan,
    search,
    from,
    to,
}: {
    logs: Paginated<NotificationRegisterRow>;
    counts: Record<'all' | NotificationStatus, number>;
    channels: NotificationOption[];
    statuses: NotificationOption[];
    types: NotificationOption[];
    currentStatus: NotificationStatus | null;
    currentChannel: string | null;
    currentType: string | null;
    currentBorrower: { id: number; name: string } | null;
    currentLoan: { id: number; label: string } | null;
    search: string;
    from: string | null;
    to: string | null;
}) {
    const [selected, setSelected] = useState<number[]>([]);
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [sending, setSending] = useState(false);

    const withFilters = (overrides: {
        status?: NotificationStatus | null;
        channel?: string | null;
        type?: string | null;
        borrower?: number | null;
        loan?: number | null;
        from?: string | null;
        to?: string | null;
    }) => {
        const status =
            overrides.status !== undefined ? overrides.status : currentStatus;
        const channel =
            overrides.channel !== undefined
                ? overrides.channel
                : currentChannel;
        const type =
            overrides.type !== undefined ? overrides.type : currentType;
        const borrower =
            overrides.borrower !== undefined
                ? overrides.borrower
                : (currentBorrower?.id ?? null);
        const loan =
            overrides.loan !== undefined
                ? overrides.loan
                : (currentLoan?.id ?? null);
        const rangeFrom = overrides.from !== undefined ? overrides.from : from;
        const rangeTo = overrides.to !== undefined ? overrides.to : to;

        return notificationsIndex({
            query: {
                ...(status ? { status } : {}),
                ...(channel ? { channel } : {}),
                ...(type ? { type } : {}),
                ...(borrower ? { borrower } : {}),
                ...(loan ? { loan } : {}),
                ...(rangeFrom ? { from: rangeFrom } : {}),
                ...(rangeTo ? { to: rangeTo } : {}),
                ...(search ? { search } : {}),
                ...(logs.per_page !== 15 ? { per_page: logs.per_page } : {}),
            },
        });
    };

    const navigate = (url: string) => {
        router.get(url, {}, { preserveScroll: true, preserveState: true });
    };

    const changeChannel = (value: string) => {
        navigate(
            withFilters({
                channel: value === 'all' ? null : value,
            }).url,
        );
    };

    const changeType = (value: string) => {
        navigate(
            withFilters({
                type: value === 'all' ? null : value,
            }).url,
        );
    };

    const changeRange = (rangeFrom: string | null, rangeTo: string | null) => {
        navigate(withFilters({ from: rangeFrom, to: rangeTo }).url);
    };

    const resend = (log: NotificationRegisterRow) => {
        if (log.loan_id === null) {
            return;
        }

        router.post(
            resendNotification({
                loan: log.loan_id,
                notificationLog: log.id,
            }).url,
            {},
            { preserveScroll: true },
        );
    };

    const selectableIds = logs.data
        .filter(isBulkResendable)
        .map((log) => log.id);
    const allSelected =
        selectableIds.length > 0 &&
        selectableIds.every((id) => selected.includes(id));
    const someSelected = selected.length > 0 && !allSelected;

    useEffect(() => {
        setSelected((current) =>
            current.filter((id) =>
                logs.data.some((log) => log.id === id && isBulkResendable(log)),
            ),
        );
    }, [logs]);

    const submitBulkResend = () => {
        if (selected.length === 0) {
            return;
        }

        router.post(
            resendNotificationsBulk().url,
            { logs: selected },
            {
                preserveScroll: true,
                onStart: () => setSending(true),
                onFinish: () => setSending(false),
                onSuccess: () => {
                    setConfirmOpen(false);
                    setSelected([]);
                },
            },
        );
    };

    const clearFilters = notificationsIndex({
        query: logs.per_page !== 15 ? { per_page: logs.per_page } : {},
    });

    const exportUrl = exportNotifications({
        query: {
            ...(currentStatus ? { status: currentStatus } : {}),
            ...(currentChannel ? { channel: currentChannel } : {}),
            ...(currentType ? { type: currentType } : {}),
            ...(currentBorrower ? { borrower: currentBorrower.id } : {}),
            ...(currentLoan ? { loan: currentLoan.id } : {}),
            ...(from ? { from } : {}),
            ...(to ? { to } : {}),
            ...(search ? { search } : {}),
        },
    });

    const hasFilters =
        search !== '' ||
        currentStatus !== null ||
        currentChannel !== null ||
        currentType !== null ||
        currentBorrower !== null ||
        currentLoan !== null ||
        from !== null ||
        to !== null;

    const selectedChannel = channels.find(
        (channel) => channel.value === currentChannel,
    );
    const selectedType = types.find((type) => type.value === currentType);

    return (
        <>
            <Head title="Notifications" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Notifications"
                    description={
                        currentLoan
                            ? `Every email and SMS delivery recorded for ${currentLoan.label}, newest first.`
                            : currentBorrower
                              ? `Every email and SMS delivery recorded for ${currentBorrower.name}, newest first.`
                              : 'Every email and SMS delivery recorded against the book, newest first.'
                    }
                    actions={
                        <Button variant="outline" asChild>
                            <a href={exportUrl.url}>
                                <Download /> Export CSV
                            </a>
                        </Button>
                    }
                />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        title="Deliveries"
                        value={counts.all}
                        hint={`${counts.queued} queued`}
                        icon={Bell}
                    />
                    <StatCard
                        title="Sent"
                        value={counts.sent}
                        hint="Delivered successfully"
                        icon={CircleCheck}
                        tone="success"
                    />
                    <StatCard
                        title="Failed"
                        value={counts.failed}
                        hint="Need attention"
                        icon={CircleAlert}
                        tone={counts.failed > 0 ? 'danger' : 'default'}
                    />
                    <StatCard
                        title="Skipped"
                        value={counts.skipped}
                        hint="Borrower opted out or no longer applicable"
                        icon={CircleSlash}
                        tone={counts.skipped > 0 ? 'warning' : 'default'}
                    />
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <SearchInput
                        value={search}
                        placeholder="Search borrower, phone, ID or loan #"
                        label="Search notifications"
                    />

                    <Select
                        value={currentChannel ?? 'all'}
                        onValueChange={changeChannel}
                    >
                        <SelectTrigger
                            className="h-9 min-w-0 flex-1 sm:w-[150px] sm:flex-none"
                            aria-label="Filter by delivery channel"
                        >
                            <SelectValue placeholder="All channels">
                                {selectedChannel?.label ?? 'All channels'}
                            </SelectValue>
                        </SelectTrigger>

                        <SelectContent>
                            <SelectItem value="all">All channels</SelectItem>

                            {channels.map((channel) => (
                                <SelectItem
                                    key={channel.value}
                                    value={channel.value}
                                >
                                    {channel.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={currentType ?? 'all'}
                        onValueChange={changeType}
                    >
                        <SelectTrigger
                            className="h-9 min-w-0 flex-1 sm:w-[170px] sm:flex-none"
                            aria-label="Filter by notification type"
                        >
                            <SelectValue placeholder="All types">
                                {selectedType?.label ?? 'All types'}
                            </SelectValue>
                        </SelectTrigger>

                        <SelectContent>
                            <SelectItem value="all">All types</SelectItem>

                            {types.map((type) => (
                                <SelectItem key={type.value} value={type.value}>
                                    {type.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <DateRangePicker
                        from={from}
                        to={to}
                        onChange={changeRange}
                    />

                    {currentBorrower && (
                        <div className="flex items-center gap-2 rounded-full border bg-muted/50 py-1 pr-1 pl-3 text-sm">
                            <span className="text-muted-foreground">
                                Borrower
                            </span>

                            <span className="font-medium">
                                {currentBorrower.name}
                            </span>

                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-6"
                                asChild
                            >
                                <Link
                                    href={withFilters({ borrower: null })}
                                    aria-label="Clear the borrower filter"
                                    title="Clear the borrower filter"
                                    preserveScroll
                                >
                                    <X />
                                </Link>
                            </Button>
                        </div>
                    )}

                    {currentLoan && (
                        <div className="flex items-center gap-2 rounded-full border bg-muted/50 py-1 pr-1 pl-3 text-sm">
                            <span className="font-medium">
                                {currentLoan.label}
                            </span>

                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-6"
                                asChild
                            >
                                <Link
                                    href={withFilters({ loan: null })}
                                    aria-label="Clear the loan filter"
                                    title="Clear the loan filter"
                                    preserveScroll
                                >
                                    <X />
                                </Link>
                            </Button>
                        </div>
                    )}
                </div>

                <FilterTabs
                    tabs={[
                        {
                            value: 'all',
                            label: 'All',
                            count: counts.all,
                            href: withFilters({ status: null }),
                        },
                        ...statuses.map((status) => ({
                            value: status.value,
                            label: status.label,
                            count: counts[status.value as NotificationStatus],
                            href: withFilters({
                                status: status.value as NotificationStatus,
                            }),
                        })),
                    ]}
                    value={currentStatus ?? 'all'}
                    label="Filter notifications by delivery status"
                    className="min-w-0"
                />

                {selected.length > 0 && (
                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-primary/30 bg-primary/5 px-4 py-3">
                        <p className="text-sm font-medium">
                            {selected.length} notification
                            {selected.length === 1 ? '' : 's'} selected
                        </p>

                        <div className="flex flex-wrap items-center gap-2">
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => setSelected([])}
                            >
                                Clear
                            </Button>

                            <Button
                                size="sm"
                                onClick={() => setConfirmOpen(true)}
                            >
                                <Send /> Resend selected
                            </Button>
                        </div>
                    </div>
                )}

                <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    {logs.data.length === 0 ? (
                        <EmptyState
                            icon={Bell}
                            title="No notifications found"
                            description={
                                currentLoan
                                    ? `No deliveries are recorded for ${currentLoan.label}. Clear the loan filter to see the whole register.`
                                    : currentBorrower
                                      ? `No deliveries are recorded for ${currentBorrower.name}. Clear the borrower filter to see the whole register.`
                                      : hasFilters
                                        ? 'No deliveries match these filters. Try another channel, type, date range or status.'
                                        : 'Reminders, overdue notices, receipts and statements appear here once they are sent.'
                            }
                            action={
                                hasFilters && (
                                    <Button asChild size="sm" variant="outline">
                                        <Link href={clearFilters}>
                                            <RotateCcw /> Clear filters
                                        </Link>
                                    </Button>
                                )
                            }
                        />
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-10">
                                        <Checkbox
                                            checked={
                                                allSelected
                                                    ? true
                                                    : someSelected
                                                      ? 'indeterminate'
                                                      : false
                                            }
                                            onCheckedChange={(checked) =>
                                                setSelected(
                                                    checked === true
                                                        ? selectableIds
                                                        : [],
                                                )
                                            }
                                            disabled={
                                                selectableIds.length === 0
                                            }
                                            aria-label="Select resendable notifications on this page"
                                        />
                                    </TableHead>
                                    <TableHead>Recorded</TableHead>
                                    <TableHead>Borrower</TableHead>
                                    <TableHead className="hidden md:table-cell">
                                        Type
                                    </TableHead>
                                    <TableHead className="hidden lg:table-cell">
                                        Channel
                                    </TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="hidden xl:table-cell">
                                        Sent
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>

                            <TableBody>
                                {logs.data.map((log) => (
                                    <TableRow
                                        key={log.id}
                                        className={cn(
                                            log.status === 'failed' &&
                                                'bg-destructive/5',
                                        )}
                                    >
                                        <TableCell>
                                            {isBulkResendable(log) && (
                                                <Checkbox
                                                    checked={selected.includes(
                                                        log.id,
                                                    )}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        setSelected(
                                                            (current) =>
                                                                checked === true
                                                                    ? [
                                                                          ...current,
                                                                          log.id,
                                                                      ]
                                                                    : current.filter(
                                                                          (
                                                                              id,
                                                                          ) =>
                                                                              id !==
                                                                              log.id,
                                                                      ),
                                                        )
                                                    }
                                                    aria-label={`Select notification ${log.id}`}
                                                />
                                            )}
                                        </TableCell>

                                        <TableCell className="whitespace-nowrap text-muted-foreground">
                                            {formatDateTime(log.created_at)}

                                            <div className="text-xs md:hidden">
                                                {log.type_label}
                                            </div>
                                        </TableCell>

                                        <TableCell>
                                            {log.borrower_name ? (
                                                <div className="font-medium">
                                                    {log.borrower_id !==
                                                    null ? (
                                                        <Link
                                                            href={showBorrower(
                                                                log.borrower_id,
                                                            )}
                                                            className="underline-offset-4 hover:underline"
                                                            prefetch
                                                        >
                                                            {log.borrower_name}
                                                        </Link>
                                                    ) : (
                                                        log.borrower_name
                                                    )}
                                                </div>
                                            ) : (
                                                <div className="text-muted-foreground">
                                                    Unknown borrower
                                                </div>
                                            )}

                                            {log.borrower_phone && (
                                                <div className="text-xs text-muted-foreground">
                                                    {log.borrower_phone}
                                                </div>
                                            )}

                                            {log.loan_id !== null && (
                                                <Link
                                                    href={showLoan(
                                                        log.loan_id,
                                                        {
                                                            query: {
                                                                tab: 'notifications',
                                                            },
                                                        },
                                                    )}
                                                    className="text-xs font-medium hover:underline"
                                                    prefetch
                                                >
                                                    Loan #{log.loan_id}
                                                </Link>
                                            )}
                                        </TableCell>

                                        <TableCell className="hidden md:table-cell">
                                            {log.type_label}
                                        </TableCell>

                                        <TableCell className="hidden lg:table-cell">
                                            {log.channel_label}
                                        </TableCell>

                                        <TableCell>
                                            <Badge
                                                variant={statusVariant(
                                                    log.status,
                                                )}
                                            >
                                                {log.status_label}
                                            </Badge>

                                            {log.error && (
                                                <div
                                                    className="mt-1 max-w-[16rem] truncate text-xs text-muted-foreground"
                                                    title={log.error}
                                                >
                                                    {log.error}
                                                </div>
                                            )}
                                        </TableCell>

                                        <TableCell className="hidden whitespace-nowrap text-muted-foreground xl:table-cell">
                                            {log.sent_at
                                                ? formatDateTime(log.sent_at)
                                                : '—'}
                                        </TableCell>

                                        <TableCell>
                                            <div className="flex justify-end gap-2">
                                                {log.can_resend &&
                                                    log.loan_id !== null && (
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            onClick={() =>
                                                                resend(log)
                                                            }
                                                        >
                                                            <Send /> Resend
                                                        </Button>
                                                    )}

                                                {log.loan_id !== null && (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={showLoan(
                                                                log.loan_id,
                                                                {
                                                                    query: {
                                                                        tab: 'notifications',
                                                                    },
                                                                },
                                                            )}
                                                            prefetch
                                                        >
                                                            Open loan
                                                        </Link>
                                                    </Button>
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </div>

                <DataPagination paginator={logs} />

                <AlertDialog open={confirmOpen} onOpenChange={setConfirmOpen}>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>
                                Resend selected notifications?
                            </AlertDialogTitle>

                            <AlertDialogDescription>
                                {selected.length} failed or skipped notification
                                {selected.length === 1 ? '' : 's'} will be
                                queued again. Deliveries whose borrower has
                                disabled the channel are skipped, and every row
                                keeps its own delivery history.
                            </AlertDialogDescription>
                        </AlertDialogHeader>

                        <AlertDialogFooter>
                            <AlertDialogCancel disabled={sending}>
                                Cancel
                            </AlertDialogCancel>

                            <Button
                                onClick={submitBulkResend}
                                disabled={sending}
                            >
                                <Send />
                                {sending ? 'Resending…' : 'Resend selected'}
                            </Button>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            </div>
        </>
    );
}

NotificationsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Administration',
            href: adminDashboard(),
        },
        {
            title: 'Notifications',
            href: notificationsIndex(),
        },
    ],
};
