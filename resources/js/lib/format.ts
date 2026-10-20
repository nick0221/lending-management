export function formatMoney(value: number | string | null | undefined): string {
    return Number(value ?? 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

export function formatPercent(value: number | string | null | undefined): string {
    return `${Number(value ?? 0).toFixed(2)}%`;
}

export function formatDate(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString();
}

/** Formats a `Y-m-d` date without shifting a day across time zones. */
export function formatDateOnly(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    const [year, month, day] = value.split('-').map(Number);

    if (!year || !month || !day) {
        return value;
    }

    return new Date(year, month - 1, day).toLocaleDateString();
}
