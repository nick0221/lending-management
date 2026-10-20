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
