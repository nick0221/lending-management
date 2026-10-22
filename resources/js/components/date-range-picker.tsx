import { format, parseISO } from 'date-fns';
import type { DateRange } from 'react-day-picker';
import { CalendarDays } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { useIsMobile } from '@/hooks/use-mobile';
import { formatDateOnly } from '@/lib/format';
import { cn } from '@/lib/utils';

type DateRangePickerProps = {
    from: string | null;
    to: string | null;
    onChange: (from: string | null, to: string | null) => void;
    align?: 'start' | 'center' | 'end';
    className?: string;
};

function toDate(value: string | null): Date | undefined {
    return value ? parseISO(value) : undefined;
}

function toDateString(value: Date | undefined): string | null {
    return value ? format(value, 'yyyy-MM-dd') : null;
}

function rangeLabel(from: string | null, to: string | null): string {
    if (from && to) {
        return `${formatDateOnly(from)} – ${formatDateOnly(to)}`;
    }

    if (from) {
        return `From ${formatDateOnly(from)}`;
    }

    if (to) {
        return `Until ${formatDateOnly(to)}`;
    }

    return 'Any date';
}

/** Shared from/to date filter backed by a range calendar in a popover. */
export function DateRangePicker({
    from,
    to,
    onChange,
    align = 'start',
    className,
}: DateRangePickerProps) {
    const isMobile = useIsMobile();
    const [open, setOpen] = useState(false);
    const [draft, setDraft] = useState<DateRange | undefined>({
        from: toDate(from),
        to: toDate(to),
    });

    const handleOpenChange = (nextOpen: boolean) => {
        setOpen(nextOpen);

        if (nextOpen) {
            setDraft({ from: toDate(from), to: toDate(to) });
        }
    };

    const clear = () => {
        setDraft(undefined);
        onChange(null, null);
        setOpen(false);
    };

    const apply = () => {
        onChange(toDateString(draft?.from), toDateString(draft?.to));
        setOpen(false);
    };

    return (
        <Popover open={open} onOpenChange={handleOpenChange}>
            <PopoverTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className={cn(
                        'justify-start gap-2 font-normal',
                        !from && !to && 'text-muted-foreground',
                        className,
                    )}
                >
                    <CalendarDays />
                    {rangeLabel(from, to)}
                </Button>
            </PopoverTrigger>

            <PopoverContent
                align={align}
                className="max-h-[calc(100vh-2rem)] w-auto max-w-[calc(100vw-2rem)] overflow-y-auto p-0"
            >
                <Calendar
                    mode="range"
                    selected={draft}
                    onSelect={setDraft}
                    defaultMonth={draft?.from ?? draft?.to ?? new Date()}
                    numberOfMonths={isMobile ? 1 : 2}
                    autoFocus
                />

                <div className="flex items-center justify-between gap-2 border-t p-3">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={clear}
                    >
                        Clear
                    </Button>

                    <Button type="button" size="sm" onClick={apply}>
                        Apply
                    </Button>
                </div>
            </PopoverContent>
        </Popover>
    );
}
