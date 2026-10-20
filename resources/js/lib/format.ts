export function formatMoney(value: number | string | null | undefined): string {
    return Number(value ?? 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

export function formatPercent(
    value: number | string | null | undefined,
): string {
    return `${Number(value ?? 0).toFixed(2)}%`;
}

export function formatDate(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString();
}

/** Formats a full timestamp (date and time) in the viewer's locale. */
export function formatDateTime(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

/** Formats a timestamp relative to now, e.g. "2 hours ago". */
export function formatRelativeTime(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    const difference = date.getTime() - Date.now();
    const units: [Intl.RelativeTimeFormatUnit, number][] = [
        ['year', 365 * 24 * 60 * 60 * 1000],
        ['month', 30 * 24 * 60 * 60 * 1000],
        ['week', 7 * 24 * 60 * 60 * 1000],
        ['day', 24 * 60 * 60 * 1000],
        ['hour', 60 * 60 * 1000],
        ['minute', 60 * 1000],
        ['second', 1000],
    ];
    const formatter = new Intl.RelativeTimeFormat(undefined, {
        numeric: 'auto',
    });

    for (const [unit, milliseconds] of units) {
        if (Math.abs(difference) >= milliseconds || unit === 'second') {
            return formatter.format(
                Math.round(difference / milliseconds),
                unit,
            );
        }
    }

    return formatter.format(0, 'second');
}

/** Formats a `Y-m-d` date or ISO timestamp without shifting the day. */
export function formatDateOnly(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    const [date] = value.split('T');
    const [year, month, day] = date.split('-').map(Number);

    if (!year || !month || !day) {
        return value;
    }

    return new Date(year, month - 1, day).toLocaleDateString();
}
