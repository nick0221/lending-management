import { Head, Link, router } from '@inertiajs/react';
import { Eye, Filter, RotateCcw } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { DataPagination } from '@/components/data-pagination';
import { PageHeader } from '@/components/page-header';
import { SectionCard } from '@/components/section-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
import { formatDateTime, formatRelativeTime } from '@/lib/format';
import { index as activityIndex } from '@/routes/admin/activity';
import type {
    ActivityLogFilters,
    ActivityLogRow,
    Paginated,
    UserOption,
} from '@/types';

const ALL = '__all__';

const toneByEvent: Record<string, string> = {
    created: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
    updated: 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
    deleted: 'bg-red-500/10 text-red-700 dark:text-red-400',
    login: 'bg-sky-500/10 text-sky-700 dark:text-sky-400',
    logout: 'bg-muted text-muted-foreground',
    login_failed: 'bg-red-500/10 text-red-700 dark:text-red-400',
};

type EventOption = {
    value: string;
    label: string;
};

export default function ActivityIndex({
    logs,
    filters,
    events,
    types,
    users,
}: {
    logs: Paginated<ActivityLogRow>;
    filters: ActivityLogFilters;
    events: EventOption[];
    types: string[];
    users: UserOption[];
}) {
    const [event, setEvent] = useState(filters.event ?? ALL);
    const [type, setType] = useState(filters.type ?? ALL);
    const [user, setUser] = useState(filters.user ? String(filters.user) : ALL);
    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');
    const [selected, setSelected] = useState<ActivityLogRow | null>(null);

    const applyFilters = (formEvent: FormEvent<HTMLFormElement>) => {
        formEvent.preventDefault();

        router.get(
            activityIndex().url,
            {
                ...(event !== ALL ? { event } : {}),
                ...(type !== ALL ? { type } : {}),
                ...(user !== ALL ? { user } : {}),
                ...(from ? { from } : {}),
                ...(to ? { to } : {}),
            },
            { preserveScroll: true, preserveState: true },
        );
    };

    return (
        <>
            <Head title="Activity log" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Activity log"
                    description="Sign-ins and changes to accounts, borrowers, products and loans."
                />

                <SectionCard
                    title="Filters"
                    description="Narrow the trail by event, record type, actor or date."
                    contentClassName="p-4"
                >
                    <form
                        onSubmit={applyFilters}
                        className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5"
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="event">Event</Label>

                            <Select value={event} onValueChange={setEvent}>
                                <SelectTrigger id="event">
                                    <SelectValue placeholder="Any event" />
                                </SelectTrigger>

                                <SelectContent>
                                    <SelectItem value={ALL}>
                                        Any event
                                    </SelectItem>
                                    {events.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="type">Record type</Label>

                            <Select value={type} onValueChange={setType}>
                                <SelectTrigger id="type">
                                    <SelectValue placeholder="Any record" />
                                </SelectTrigger>

                                <SelectContent>
                                    <SelectItem value={ALL}>
                                        Any record
                                    </SelectItem>
                                    {types.map((option) => (
                                        <SelectItem key={option} value={option}>
                                            {option}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="user">User</Label>

                            <Select value={user} onValueChange={setUser}>
                                <SelectTrigger id="user">
                                    <SelectValue placeholder="Any user" />
                                </SelectTrigger>

                                <SelectContent>
                                    <SelectItem value={ALL}>
                                        Any user
                                    </SelectItem>
                                    {users.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={String(option.value)}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="from">From</Label>
                            <Input
                                id="from"
                                type="date"
                                value={from}
                                onChange={(changeEvent) =>
                                    setFrom(changeEvent.target.value)
                                }
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="to">To</Label>
                            <Input
                                id="to"
                                type="date"
                                value={to}
                                onChange={(changeEvent) =>
                                    setTo(changeEvent.target.value)
                                }
                            />
                        </div>

                        <div className="flex items-end gap-2 sm:col-span-2 lg:col-span-5">
                            <Button type="submit">
                                <Filter /> Apply filters
                            </Button>

                            <Button asChild variant="outline">
                                <Link href={activityIndex()} preserveScroll>
                                    <RotateCcw /> Reset
                                </Link>
                            </Button>
                        </div>
                    </form>
                </SectionCard>

                <SectionCard
                    title="Audit trail"
                    description={`${logs.total} event${
                        logs.total === 1 ? '' : 's'
                    } recorded.`}
                >
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>When</TableHead>
                                <TableHead>Actor</TableHead>
                                <TableHead>Event</TableHead>
                                <TableHead>Record</TableHead>
                                <TableHead>Description</TableHead>
                                <TableHead className="w-16">
                                    <span className="sr-only">Actions</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>

                        <TableBody>
                            {logs.data.map((log) => (
                                <TableRow key={log.id}>
                                    <TableCell className="whitespace-nowrap text-muted-foreground">
                                        <span
                                            title={formatDateTime(
                                                log.created_at,
                                            )}
                                        >
                                            {formatRelativeTime(log.created_at)}
                                        </span>
                                    </TableCell>

                                    <TableCell>
                                        {log.user_name ? (
                                            <div>
                                                <p className="font-medium">
                                                    {log.user_name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {log.user_email}
                                                </p>
                                            </div>
                                        ) : (
                                            <span className="text-muted-foreground">
                                                System
                                            </span>
                                        )}
                                    </TableCell>

                                    <TableCell>
                                        <Badge
                                            variant="secondary"
                                            className={
                                                toneByEvent[log.event] ??
                                                'bg-muted text-muted-foreground'
                                            }
                                        >
                                            {log.event_label}
                                        </Badge>
                                    </TableCell>

                                    <TableCell className="text-muted-foreground">
                                        {log.subject_type
                                            ? `${log.subject_type} #${log.subject_id}`
                                            : '—'}
                                    </TableCell>

                                    <TableCell>
                                        <p className="max-w-md">
                                            {log.description}
                                        </p>

                                        {log.changes.length > 0 && (
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {log.changes
                                                    .slice(0, 4)
                                                    .map((change) => change.key)
                                                    .join(' · ')}
                                                {log.changes.length > 4 &&
                                                    ` +${log.changes.length - 4} more`}
                                            </p>
                                        )}
                                    </TableCell>

                                    <TableCell>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => setSelected(log)}
                                            aria-label={`View ${log.event_label} details`}
                                        >
                                            <Eye /> View
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}

                            {logs.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={6}
                                        className="py-8 text-center text-muted-foreground"
                                    >
                                        No activity recorded for these filters.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </SectionCard>

                <DataPagination paginator={logs} />
            </div>

            <Sheet
                open={selected !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setSelected(null);
                    }
                }}
            >
                <SheetContent className="w-full sm:max-w-lg">
                    {selected && (
                        <>
                            <SheetHeader className="border-b">
                                <SheetTitle>{selected.event_label}</SheetTitle>
                                <SheetDescription>
                                    {selected.description}
                                </SheetDescription>
                            </SheetHeader>

                            <div className="flex-1 space-y-6 overflow-y-auto px-4 pb-6">
                                <dl className="grid grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <dt className="text-xs text-muted-foreground uppercase">
                                            Actor
                                        </dt>
                                        <dd className="font-medium">
                                            {selected.user_name ?? 'System'}
                                        </dd>
                                        {selected.user_email && (
                                            <dd className="text-xs text-muted-foreground">
                                                {selected.user_email}
                                            </dd>
                                        )}
                                    </div>

                                    <div>
                                        <dt className="text-xs text-muted-foreground uppercase">
                                            When
                                        </dt>
                                        <dd className="font-medium">
                                            {formatDateTime(
                                                selected.created_at,
                                            )}
                                        </dd>
                                        <dd className="text-xs text-muted-foreground">
                                            {formatRelativeTime(
                                                selected.created_at,
                                            )}
                                        </dd>
                                    </div>

                                    <div>
                                        <dt className="text-xs text-muted-foreground uppercase">
                                            Record
                                        </dt>
                                        <dd className="font-medium">
                                            {selected.subject_type
                                                ? `${selected.subject_type} #${selected.subject_id}`
                                                : '—'}
                                        </dd>
                                    </div>

                                    <div>
                                        <dt className="text-xs text-muted-foreground uppercase">
                                            IP address
                                        </dt>
                                        <dd className="font-medium">
                                            {selected.ip_address ?? '—'}
                                        </dd>
                                    </div>

                                    {selected.user_agent && (
                                        <div className="col-span-2">
                                            <dt className="text-xs text-muted-foreground uppercase">
                                                User agent
                                            </dt>
                                            <dd className="text-xs break-words text-muted-foreground">
                                                {selected.user_agent}
                                            </dd>
                                        </div>
                                    )}
                                </dl>

                                <div>
                                    <h3 className="mb-2 text-sm font-medium">
                                        Field changes
                                    </h3>

                                    {selected.changes.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">
                                            No field changes recorded.
                                        </p>
                                    ) : (
                                        <div className="overflow-hidden rounded-lg border">
                                            <Table>
                                                <TableHeader>
                                                    <TableRow>
                                                        <TableHead>
                                                            Field
                                                        </TableHead>
                                                        <TableHead>
                                                            Before
                                                        </TableHead>
                                                        <TableHead>
                                                            After
                                                        </TableHead>
                                                    </TableRow>
                                                </TableHeader>

                                                <TableBody>
                                                    {selected.changes.map(
                                                        (change) => (
                                                            <TableRow
                                                                key={change.key}
                                                            >
                                                                <TableCell className="font-medium whitespace-normal">
                                                                    {change.key}
                                                                </TableCell>
                                                                <TableCell className="break-words whitespace-normal text-muted-foreground">
                                                                    {change.old ??
                                                                        '—'}
                                                                </TableCell>
                                                                <TableCell className="break-words whitespace-normal">
                                                                    {change.new ??
                                                                        '—'}
                                                                </TableCell>
                                                            </TableRow>
                                                        ),
                                                    )}
                                                </TableBody>
                                            </Table>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </>
                    )}
                </SheetContent>
            </Sheet>
        </>
    );
}
