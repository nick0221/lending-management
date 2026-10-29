import { Check, ChevronsUpDown } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import type { BorrowerOption } from '@/types';

/**
 * Searchable borrower picker. A plain select gets unwieldy once there are
 * hundreds of borrowers, so the list is filtered as the name is typed and the
 * phone number is shown to tell namesakes apart.
 */
export function BorrowerPicker({
    id,
    value,
    onChange,
    options,
    placeholder = 'Select a borrower',
    invalid = false,
}: {
    id: string;
    value: string;
    onChange: (value: string) => void;
    options: BorrowerOption[];
    placeholder?: string;
    invalid?: boolean;
}) {
    const [open, setOpen] = useState(false);

    const selected = options.find((option) => String(option.value) === value);

    const select = (nextValue: string): void => {
        onChange(nextValue);
        setOpen(false);
    };

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    id={id}
                    type="button"
                    variant="outline"
                    role="combobox"
                    aria-expanded={open}
                    aria-invalid={invalid}
                    className="w-full justify-between font-normal"
                >
                    <span
                        className={cn(
                            'truncate',
                            !selected && 'text-muted-foreground',
                        )}
                    >
                        {selected?.label ?? placeholder}
                    </span>
                    <ChevronsUpDown className="size-4 shrink-0 opacity-50" />
                </Button>
            </PopoverTrigger>

            <PopoverContent
                align="start"
                collisionPadding={8}
                className="max-h-(--radix-popover-content-available-height) w-(--radix-popover-trigger-width) min-w-64 overflow-hidden p-0"
            >
                <Command>
                    <CommandInput placeholder="Search by borrower name..." />

                    <CommandList className="max-h-[min(18rem,calc(var(--radix-popover-content-available-height)-3rem))]">
                        <CommandEmpty>
                            No borrower matches that name.
                        </CommandEmpty>

                        <CommandGroup>
                            {options.map((option) => {
                                const optionValue = String(option.value);
                                const isSelected = optionValue === value;

                                return (
                                    <CommandItem
                                        key={optionValue}
                                        value={`${option.label} ${option.phone ?? ''}`}
                                        onSelect={() => select(optionValue)}
                                    >
                                        <Check
                                            className={cn(
                                                'size-4',
                                                isSelected
                                                    ? 'opacity-100'
                                                    : 'opacity-0',
                                            )}
                                        />

                                        <div className="flex min-w-0 flex-col">
                                            <span className="truncate">
                                                {option.label}
                                            </span>

                                            <span className="text-xs text-muted-foreground">
                                                {option.phone ?? 'No phone'}
                                            </span>
                                        </div>
                                    </CommandItem>
                                );
                            })}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
