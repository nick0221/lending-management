import { Head, Link, router } from '@inertiajs/react';
import {
    Check,
    Copy,
    RefreshCw,
    RotateCcw,
    ScrollText,
    Trash2,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { SectionCard } from '@/components/section-card';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useClipboard } from '@/hooks/use-clipboard';
import { formatDateTime, formatRelativeTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as systemIndex } from '@/routes/admin/system';
import {
    destroy as forgetFailedJob,
    retry as retryFailedJob,
    retryAll as retryAllFailedJobs,
} from '@/routes/admin/system/failed-jobs';

type StatusTone = 'ok' | 'warn' | 'bad';

const toneClass: Record<StatusTone, string> = {
    ok: 'text-emerald-600 dark:text-emerald-400',
    warn: 'text-amber-600 dark:text-amber-400',
    bad: 'text-destructive',
};

type ScheduledCommand = {
    command: string;
    expression: string;
    next_run_at: string;
    last_run_at: string | null;
    max_age_hours: number;
    stale: boolean;
};

type FailedJob = {
    uuid: string;
    name: string;
    queue: string;
    failed_at: string;
    exception: string;
    exception_detail: string;
};

export default function SystemStatus({
    app,
    database,
    queue,
    failed_jobs: failedJobs,
    scheduled,
    mail,
    sms,
    storage,
}: {
    app: {
        name: string;
        environment: string;
        debug: boolean;
        url: string;
        timezone: string;
        laravel: string;
        php: string;
        now: string;
    };
    database: {
        connection: string;
        driver: string;
        status: string;
        cache: string;
        session: string;
    };
    queue: {
        connection: string;
        pending: number | null;
        failed: number | null;
        oldest_pending_at: string | null;
    };
    scheduled: ScheduledCommand[];
    failed_jobs: FailedJob[];
    mail: {
        mailer: string;
        from_address: string | null;
        from_name: string | null;
    };
    sms: {
        driver: string;
        configured: boolean;
        endpoint: string | null;
        method: string;
        format: string;
        from: string | null;
        window_days: number;
        deliveries: {
            sent: number;
            failed: number;
            skipped: number;
            queued: number;
        };
        last_sent_at: string | null;
        last_failure: {
            message: string;
            at: string | null;
        } | null;
    };
    storage: {
        disk: string;
        path: string;
        free: string | null;
        total: string | null;
        used_percent: number | null;
    };
}) {
    const [, copy] = useClipboard();
    const [copiedDiagnostics, setCopiedDiagnostics] = useState(false);

    const diagnostics = [
        `${app.name} system diagnostics`,
        `Generated: ${formatDateTime(app.now)}`,
        `Environment: ${app.environment} (debug ${app.debug ? 'on' : 'off'})`,
        `URL: ${app.url}`,
        `Laravel: ${app.laravel}; PHP: ${app.php}`,
        `Database: ${database.connection} (${database.driver}) - ${database.status}`,
        `Queue: ${queue.connection} - pending ${queue.pending ?? 'n/a'}, failed ${queue.failed ?? 'n/a'}`,
        `Mail: ${mail.mailer} from ${mail.from_address ?? 'not configured'}`,
        `SMS: ${sms.driver} - sent ${sms.deliveries.sent}, failed ${sms.deliveries.failed}, skipped ${sms.deliveries.skipped} in the last ${sms.window_days} days`,
        `Storage: ${storage.free ?? 'unknown'} free of ${storage.total ?? 'unknown'} (${storage.used_percent ?? 'unknown'}% used)`,
    ].join('\n');

    const copyDiagnostics = async () => {
        const copied = await copy(diagnostics);

        if (!copied) {
            return;
        }

        setCopiedDiagnostics(true);
        window.setTimeout(() => setCopiedDiagnostics(false), 2000);
    };

    return (
        <>
            <Head title="System status" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="System status"
                    description="Queue, scheduler, mail, SMS and storage health for this environment."
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={copyDiagnostics}
                            >
                                {copiedDiagnostics ? <Check /> : <Copy />}
                                {copiedDiagnostics
                                    ? 'Copied'
                                    : 'Copy diagnostics'}
                            </Button>

                            <Button asChild variant="outline">
                                <Link href={systemIndex()} preserveScroll>
                                    <RefreshCw /> Refresh
                                </Link>
                            </Button>
                        </div>
                    }
                />

                <div className="grid gap-6 lg:grid-cols-2">
                    <SectionCard
                        title="Application"
                        description="Runtime versions and environment settings."
                        contentClassName="p-4"
                    >
                        <dl className="divide-y">
                            <DetailRow label="Name" value={app.name} />
                            <DetailRow
                                label="Environment"
                                value={
                                    <span
                                        className={cn(
                                            'capitalize',
                                            app.environment === 'production'
                                                ? 'text-emerald-600 dark:text-emerald-400'
                                                : 'text-amber-600 dark:text-amber-400',
                                        )}
                                    >
                                        {app.environment}
                                    </span>
                                }
                            />
                            <DetailRow
                                label="Debug"
                                value={app.debug ? 'On' : 'Off'}
                                tone={app.debug ? 'warn' : 'ok'}
                            />
                            <DetailRow label="URL" value={app.url} />
                            <DetailRow label="Timezone" value={app.timezone} />
                            <DetailRow
                                label="Server time"
                                value={formatDateTime(app.now)}
                            />
                            <DetailRow
                                label="Laravel"
                                value={`v${app.laravel}`}
                            />
                            <DetailRow label="PHP" value={app.php} />
                        </dl>
                    </SectionCard>

                    <SectionCard
                        title="Database"
                        description="Connection health and the configured stores."
                        contentClassName="p-4"
                    >
                        <dl className="divide-y">
                            <DetailRow
                                label="Connection"
                                value={database.connection}
                            />
                            <DetailRow label="Driver" value={database.driver} />
                            <DetailRow
                                label="Status"
                                value={
                                    <span
                                        className={cn(
                                            'inline-flex items-center gap-1.5',
                                            database.status === 'Connected'
                                                ? toneClass.ok
                                                : toneClass.bad,
                                        )}
                                    >
                                        <span className="size-1.5 rounded-full bg-current" />
                                        {database.status}
                                    </span>
                                }
                            />
                            <DetailRow
                                label="Cache store"
                                value={database.cache}
                            />
                            <DetailRow
                                label="Session driver"
                                value={database.session}
                            />
                        </dl>
                    </SectionCard>

                    <SectionCard
                        title="Queue"
                        description={
                            queue.connection === 'database'
                                ? 'Backlog for the database queue driver.'
                                : 'Counts are only shown for the database queue driver.'
                        }
                        contentClassName="space-y-4 p-4"
                    >
                        <dl className="divide-y">
                            <DetailRow
                                label="Connection"
                                value={queue.connection}
                            />
                        </dl>

                        <div className="grid grid-cols-3 gap-3">
                            <Metric
                                label="Pending"
                                value={queue.pending ?? '—'}
                            />
                            <Metric
                                label="Failed"
                                value={queue.failed ?? '—'}
                                tone={
                                    queue.failed !== null && queue.failed > 0
                                        ? 'bad'
                                        : undefined
                                }
                            />
                            <Metric
                                label="Oldest"
                                value={
                                    queue.oldest_pending_at
                                        ? formatRelativeTime(
                                              queue.oldest_pending_at,
                                          )
                                        : '—'
                                }
                            />
                        </div>
                    </SectionCard>

                    <SectionCard
                        title="Failed jobs"
                        description="The most recent jobs the queue gave up on. Retry requeues the job; forget removes it without retrying."
                        className="lg:col-span-2"
                        actions={
                            failedJobs.length > 0 && (
                                <RetryAllFailedJobs count={failedJobs.length} />
                            )
                        }
                    >
                        {failedJobs.length === 0 ? (
                            <p className="p-4 text-sm text-muted-foreground">
                                No failed jobs. The queue is clear.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Job</TableHead>
                                            <TableHead className="hidden md:table-cell">
                                                Queue
                                            </TableHead>
                                            <TableHead>Failed</TableHead>
                                            <TableHead className="hidden lg:table-cell">
                                                Reason
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Actions
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>

                                    <TableBody>
                                        {failedJobs.map((job) => (
                                            <TableRow key={job.uuid}>
                                                <TableCell className="max-w-56 font-mono text-xs break-all">
                                                    {job.name}
                                                </TableCell>
                                                <TableCell className="hidden text-muted-foreground md:table-cell">
                                                    {job.queue}
                                                </TableCell>
                                                <TableCell className="text-muted-foreground">
                                                    <span
                                                        title={formatDateTime(
                                                            job.failed_at,
                                                        )}
                                                    >
                                                        {formatRelativeTime(
                                                            job.failed_at,
                                                        )}
                                                    </span>
                                                </TableCell>
                                                <TableCell className="hidden max-w-md text-xs break-words text-muted-foreground lg:table-cell">
                                                    {job.exception}
                                                </TableCell>
                                                <TableCell>
                                                    <FailedJobActions
                                                        job={job}
                                                    />
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        )}

                        {queue.failed !== null &&
                            queue.failed > failedJobs.length && (
                                <p className="border-t px-4 py-3 text-xs text-muted-foreground">
                                    Showing the {failedJobs.length} most recent
                                    of {queue.failed} failed jobs. Retry all
                                    requeues every one of them, including those
                                    not listed.
                                </p>
                            )}
                    </SectionCard>

                    <SectionCard
                        title="Mail"
                        description="Outgoing mail configuration for borrower notifications."
                        contentClassName="p-4"
                    >
                        <dl className="divide-y">
                            <DetailRow label="Mailer" value={mail.mailer} />
                            <DetailRow
                                label="From address"
                                value={mail.from_address ?? 'Not configured'}
                            />
                            <DetailRow
                                label="From name"
                                value={mail.from_name ?? 'Not configured'}
                            />
                        </dl>

                        <p className="mt-4 text-xs text-muted-foreground">
                            Emails are queued when a queue connection is
                            configured, so the queue worker also affects mail
                            delivery.
                        </p>
                    </SectionCard>

                    <SectionCard
                        title="SMS"
                        description="Gateway configuration and delivery health from the notification log."
                        className="lg:col-span-2"
                        contentClassName="space-y-4 p-4"
                    >
                        <dl className="divide-y">
                            <DetailRow
                                label="Driver"
                                value={
                                    sms.driver === 'http'
                                        ? 'HTTP gateway'
                                        : sms.driver === 'log'
                                          ? 'Log only'
                                          : sms.driver
                                }
                            />
                            <DetailRow
                                label="Status"
                                value={
                                    sms.configured
                                        ? 'Configured'
                                        : 'Needs an endpoint'
                                }
                                tone={sms.configured ? 'ok' : 'bad'}
                            />
                            <DetailRow
                                label="Endpoint"
                                value={
                                    sms.endpoint ??
                                    (sms.driver === 'log'
                                        ? 'Log only'
                                        : 'Not configured')
                                }
                            />
                            <DetailRow
                                label="Request"
                                value={`${sms.method} · ${sms.format}`}
                            />
                            <DetailRow
                                label="Sender ID"
                                value={sms.from ?? 'Not configured'}
                            />
                            <DetailRow
                                label="Last delivered"
                                value={
                                    sms.last_sent_at ? (
                                        <span
                                            title={formatDateTime(
                                                sms.last_sent_at,
                                            )}
                                        >
                                            {formatRelativeTime(
                                                sms.last_sent_at,
                                            )}
                                        </span>
                                    ) : (
                                        'Never'
                                    )
                                }
                            />
                        </dl>

                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <Metric label="Sent" value={sms.deliveries.sent} />
                            <Metric
                                label="Failed"
                                value={sms.deliveries.failed}
                                tone={
                                    sms.deliveries.failed > 0
                                        ? 'bad'
                                        : undefined
                                }
                            />
                            <Metric
                                label="Skipped"
                                value={sms.deliveries.skipped}
                            />
                            <Metric
                                label="Queued"
                                value={sms.deliveries.queued}
                            />
                        </div>

                        <p className="text-xs text-muted-foreground">
                            Counts cover the last {sms.window_days} days. A log
                            driver records messages without contacting a
                            gateway.
                        </p>

                        {sms.last_failure && (
                            <div className="rounded-lg border border-destructive/40 bg-destructive/5 p-3">
                                <p className="text-xs font-medium text-destructive">
                                    Last failure
                                    {sms.last_failure.at
                                        ? ` · ${formatRelativeTime(sms.last_failure.at)}`
                                        : ''}
                                </p>
                                <p className="mt-1 text-xs break-words text-muted-foreground">
                                    {sms.last_failure.message}
                                </p>
                            </div>
                        )}
                    </SectionCard>

                    <SectionCard
                        title="Scheduler"
                        description="Scheduled commands, when each one runs next and when it last completed. A command is overdue when it has not completed within its expected window."
                        className="lg:col-span-2"
                    >
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Command</TableHead>
                                    <TableHead>Cron</TableHead>
                                    <TableHead>Last run</TableHead>
                                    <TableHead>Next run</TableHead>
                                </TableRow>
                            </TableHeader>

                            <TableBody>
                                {scheduled.map((event) => (
                                    <TableRow key={event.command}>
                                        <TableCell className="font-mono text-xs">
                                            {event.command}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {event.expression}
                                        </TableCell>
                                        <TableCell>
                                            {event.last_run_at ? (
                                                <span
                                                    className={cn(
                                                        event.stale
                                                            ? 'text-amber-600 dark:text-amber-400'
                                                            : 'text-muted-foreground',
                                                    )}
                                                    title={formatDateTime(
                                                        event.last_run_at,
                                                    )}
                                                >
                                                    {formatRelativeTime(
                                                        event.last_run_at,
                                                    )}
                                                    {event.stale &&
                                                        ' · overdue'}
                                                </span>
                                            ) : (
                                                <span className="text-amber-600 dark:text-amber-400">
                                                    Not recorded
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            <span
                                                title={formatDateTime(
                                                    event.next_run_at,
                                                )}
                                            >
                                                {formatRelativeTime(
                                                    event.next_run_at,
                                                )}
                                            </span>
                                        </TableCell>
                                    </TableRow>
                                ))}

                                {scheduled.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={4}
                                            className="py-8 text-center text-muted-foreground"
                                        >
                                            No scheduled commands.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </SectionCard>

                    <SectionCard
                        title="Storage"
                        description="Disk space on the server that holds uploads and logs."
                        className="lg:col-span-2"
                        contentClassName="space-y-4 p-4"
                    >
                        <dl className="divide-y">
                            <DetailRow
                                label="Default disk"
                                value={storage.disk}
                            />
                            <DetailRow
                                label="Path"
                                value={
                                    <span className="font-mono text-xs break-all">
                                        {storage.path}
                                    </span>
                                }
                            />
                            <DetailRow
                                label="Free space"
                                value={storage.free ?? 'Unknown'}
                            />
                            <DetailRow
                                label="Total space"
                                value={storage.total ?? 'Unknown'}
                            />
                        </dl>

                        {storage.used_percent !== null && (
                            <div className="space-y-1">
                                <div className="flex items-center justify-between text-xs text-muted-foreground">
                                    <span>Used</span>
                                    <span>{storage.used_percent}%</span>
                                </div>

                                <div className="h-2 w-full overflow-hidden rounded-full bg-muted">
                                    <div
                                        className="h-full rounded-full bg-primary"
                                        style={{
                                            width: `${Math.min(storage.used_percent, 100)}%`,
                                        }}
                                    />
                                </div>
                            </div>
                        )}
                    </SectionCard>
                </div>
            </div>
        </>
    );
}

function RetryAllFailedJobs({ count }: { count: number }) {
    const [confirmOpen, setConfirmOpen] = useState(false);

    const retryAll = () => {
        router.post(
            retryAllFailedJobs.url(),
            {},
            {
                preserveScroll: true,
                onFinish: () => setConfirmOpen(false),
            },
        );
    };

    return (
        <>
            <Button
                size="sm"
                variant="outline"
                onClick={() => setConfirmOpen(true)}
            >
                <RotateCcw /> Retry all
            </Button>

            <AlertDialog open={confirmOpen} onOpenChange={setConfirmOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            Retry every failed job?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            Every failed job in the queue will be pushed back
                            onto the queue and retried, including the {count}{' '}
                            listed here. Jobs that fail again come back to this
                            list.
                        </AlertDialogDescription>
                    </AlertDialogHeader>

                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <Button onClick={retryAll}>
                            <RotateCcw /> Retry all
                        </Button>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}

function FailedJobActions({ job }: { job: FailedJob }) {
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [traceOpen, setTraceOpen] = useState(false);
    const [, copy] = useClipboard();
    const [copiedTrace, setCopiedTrace] = useState(false);

    const retry = () => {
        router.post(retryFailedJob.url(job.uuid), {}, { preserveScroll: true });
    };

    const forget = () => {
        router.delete(forgetFailedJob.url(job.uuid), {
            preserveScroll: true,
            onFinish: () => setConfirmOpen(false),
        });
    };

    const copyTrace = async () => {
        const copied = await copy(job.exception_detail);

        if (!copied) {
            return;
        }

        setCopiedTrace(true);
        window.setTimeout(() => setCopiedTrace(false), 2000);
    };

    return (
        <div className="flex items-center justify-end gap-1">
            <Button size="sm" variant="outline" onClick={retry}>
                <RotateCcw /> Retry
            </Button>

            <Button
                size="sm"
                variant="ghost"
                onClick={() => setTraceOpen(true)}
                aria-label={`View the trace for failed job ${job.name}`}
                title="View trace"
            >
                <ScrollText />
            </Button>

            <Button
                size="sm"
                variant="ghost"
                onClick={() => setConfirmOpen(true)}
                aria-label={`Forget failed job ${job.name}`}
            >
                <Trash2 />
            </Button>

            <Sheet open={traceOpen} onOpenChange={setTraceOpen}>
                <SheetContent className="w-full sm:max-w-2xl">
                    <SheetHeader className="border-b">
                        <SheetTitle className="font-mono text-sm break-all">
                            {job.name}
                        </SheetTitle>
                        <SheetDescription>
                            {job.queue} queue · failed{' '}
                            {formatDateTime(job.failed_at)}
                        </SheetDescription>
                    </SheetHeader>

                    <div className="flex-1 space-y-3 overflow-y-auto px-4 pb-6">
                        <Button size="sm" variant="outline" onClick={copyTrace}>
                            {copiedTrace ? <Check /> : <Copy />}
                            {copiedTrace ? 'Copied' : 'Copy trace'}
                        </Button>

                        <pre className="rounded-lg border bg-muted/50 p-4 font-mono text-xs break-words whitespace-pre-wrap">
                            {job.exception_detail}
                        </pre>
                    </div>
                </SheetContent>
            </Sheet>

            <AlertDialog open={confirmOpen} onOpenChange={setConfirmOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Forget this job?</AlertDialogTitle>
                        <AlertDialogDescription>
                            The job will be removed from the failed queue
                            without being retried. This cannot be undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>

                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <Button variant="destructive" onClick={forget}>
                            Forget
                        </Button>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}

function DetailRow({
    label,
    value,
    tone,
}: {
    label: string;
    value: ReactNode;
    tone?: StatusTone;
}) {
    return (
        <div className="flex items-start justify-between gap-4 py-2 text-sm">
            <dt className="text-muted-foreground">{label}</dt>
            <dd
                className={cn(
                    'text-right font-medium break-words',
                    tone && toneClass[tone],
                )}
            >
                {value}
            </dd>
        </div>
    );
}

function Metric({
    label,
    value,
    tone,
}: {
    label: string;
    value: ReactNode;
    tone?: 'bad';
}) {
    return (
        <div className="rounded-lg border bg-muted/30 px-3 py-2">
            <p
                className={cn(
                    'text-2xl font-semibold tabular-nums',
                    tone === 'bad' && 'text-destructive',
                )}
            >
                {value}
            </p>
            <p className="text-xs text-muted-foreground">{label}</p>
        </div>
    );
}

SystemStatus.layout = {
    breadcrumbs: [
        {
            title: 'Administration',
            href: adminDashboard(),
        },
        {
            title: 'System status',
            href: systemIndex(),
        },
    ],
};
