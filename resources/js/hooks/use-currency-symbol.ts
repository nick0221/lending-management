import { usePage } from '@inertiajs/react';
import { setCurrencySymbol } from '@/lib/format';

/**
 * Keep `formatMoney` in sync with the organisation's configured currency
 * symbol. Layouts and standalone document pages call this before rendering
 * amounts.
 */
export function useCurrencySymbol(): void {
    const { currency_symbol } = usePage<{ currency_symbol?: string }>().props;

    setCurrencySymbol(currency_symbol ?? '');
}
