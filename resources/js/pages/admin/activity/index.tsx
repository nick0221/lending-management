import { Head, Link, router } from '@inertiajs/react';
import { Filter, RotateCcw } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
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
import { formatDateTime } from '@/lib/format';
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
    const [user, setUser] = useState(
        filters.user ? String(filters.user) : ALL,
    );
    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');

    const previous = logs.links.find((link) =>
        link.label.includes('Previous'),
    );
    const next = logs.links.find((link) => link.label.includes('Next'));

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
                <Heading
                    title="Activity log"
                    description="Sign-ins and changes to accounts, borrowers, products and loans."
                />

                <form
                    onSubmit={applyFilters}
                    className="grid gap-4 rounded-xl border border-sidebar-border/70 p-4 sm:grid-cols-2 lg:grid-cols-5 dark:border-sidebar-border"
                >
                    <div className="grid gap-2">
                        <Label htmlFor="event">Event</Label>

                        <Select value={event} onValueChange={setEvent}>
                            <SelectTrigger id="event">
                                <SelectValue placeholder="Any event" />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectItem value={ALL}>Any event</SelectItem>
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
                                <SelectItem value={ALL}>Any record</SelectItem>
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
                                <SelectItem value={ALL}>Any user</SelectItem>
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
                            onChange={(event) => setFrom(event.target.value)}
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="to">To</Label>
                        <Input
                            id="to"
                            type="date"
                            value={to}
                            onChange={(event) => setTo(event.target.value)}
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

                <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-xs text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        When
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        User
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Event
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Description
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        IP
                                    </th>
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                                {logs.data.map((log) => (
                                    <tr key={log.id}>
                                        <td className="px-4 py-3 whitespace-nowrap text-muted-foreground">
                                            {formatDateTime(log.created_at)}
                                        </td>

                                        <td className="px-4 py-3">
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
                                        </td>

                                        <td className="px-4 py-3">
                                            <span
                                                className={`inline-flex rounded-full px-2 py-1 text-xs font-medium ${
                                                    toneByEvent[log.event] ??
                                                    'bg-muted text-muted-foreground'
                                                }`}
                                            >
                                                {log.event_label}
                                            </span>
                                        </td>

                                        <td className="px-4 py-3">
                                            <p>{log.description}</p>

                                            {log.changes.length > 0 && (
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    {log.changes
                                                        .map(
                                                            (change) =>
                                                                `${change.key}: ${change.value}`,
                                                        )
                                                        .join(' · ')}
                                                </p>
                                            )}
                                        </td>

                                        <td className="px-4 py-3 text-muted-foreground">
                                            {log.ip_address ?? '—'}
                                        </td>
                                    </tr>
                                ))}

                                {logs.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            className="px-4 py-8 text-center text-muted-foreground"
                                        >
                                            No activity recorded for these
                                            filters.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {logs.last_page > 1 && (
                    <div className="flex items-center justify-between gap-4">
                        {previous?.url ? (
                            <Button variant="outline" size="sm" asChild>
                                <Link href={previous.url} preserveScroll>
                                    Previous
                                </Link>
                            </Button>
                        ) : (
                            <Button variant="outline" size="sm" disabled>
                                Previous
                            </Button>
                        )}

                        <p className="text-sm text-muted-foreground">
                            Page {logs.current_page} of {logs.last_page}
                        </p>

                        {next?.url ? (
                            <Button variant="outline" size="sm" asChild>
                                <Link href={next.url} preserveScroll>
                                    Next
                                </Link>
                            </Button>
                        ) : (
                            <Button variant="outline" size="sm" disabled>
                                Next
                            </Button>
                        )}
                    </div>
                )}
            </div>
        </>
    );
}
