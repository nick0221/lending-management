import { Head, Link } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';
import type { ReactNode } from 'react';
import { PageHeader } from '@/components/page-header';
import { SectionCard } from '@/components/section-card';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDateTime, formatRelativeTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as systemIndex } from '@/routes/admin/system';

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
};

export default function SystemStatus({
    app,
    database,
    queue,
    scheduled,
    mail,
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
    mail: {
        mailer: string;
        from_address: string | null;
        from_name: string | null;
    };
    storage: {
        disk: string;
        path: string;
        free: string | null;
        total: string | null;
        used_percent: number | null;
    };
}) {
    return (
        <>
            <Head title="System status" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="System status"
                    description="Queue, scheduler, mail and storage health for this environment."
                    actions={
                        <Button asChild variant="outline">
                            <Link href={systemIndex()} preserveScroll>
                                <RefreshCw /> Refresh
                            </Link>
                        </Button>
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
                            <DetailRow label="Cache store" value={database.cache} />
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
                        title="Scheduler"
                        description="Scheduled commands and when each one runs next."
                        className="lg:col-span-2"
                    >
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Command</TableHead>
                                    <TableHead>Cron</TableHead>
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
                                            colSpan={3}
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
                            <DetailRow label="Default disk" value={storage.disk} />
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
