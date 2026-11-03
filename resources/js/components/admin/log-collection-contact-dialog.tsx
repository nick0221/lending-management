import { Form } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatMoney } from '@/lib/format';
import { store as logCollectionContact } from '@/routes/admin/loans/collections';
import type {
    CollectionChannel,
    CollectionChannelOption,
    CollectionOutcome,
    CollectionOutcomeOption,
    Loan,
} from '@/types';

const textareaClassName =
    'border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 flex min-h-20 w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] md:text-sm';

/** The current local time in the shape a datetime-local input expects. */
function currentLocalDateTime(): string {
    const now = new Date();

    return new Date(now.getTime() - now.getTimezoneOffset() * 60_000)
        .toISOString()
        .slice(0, 16);
}

/**
 * Log a collection contact — a call, visit, message or a borrower's promise to
 * pay — against the loan. Promises carry an amount and a date so the queue can
 * chase them on time.
 */
export function LogCollectionContactDialog({
    loan,
    open,
    onOpenChange,
    channels,
    outcomes,
    outstanding,
}: {
    loan: Loan;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    channels: CollectionChannelOption[];
    outcomes: CollectionOutcomeOption[];
    outstanding: string;
}) {
    const [channel, setChannel] = useState<CollectionChannel>(
        channels[0]?.value ?? 'call',
    );
    const [outcome, setOutcome] = useState<CollectionOutcome>('contacted');

    const selectedChannel = channels.find((option) => option.value === channel);
    const selectedOutcome = outcomes.find((option) => option.value === outcome);
    const isPromise = outcome === 'promised';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="flex flex-col gap-0 overflow-hidden p-0"
                style={{ maxHeight: 'calc(100dvh - 2rem)' }}
            >
                <DialogHeader className="p-6 pb-4">
                    <DialogTitle>Log collection contact</DialogTitle>
                    <DialogDescription>
                        Record the conversation with {loan.borrower_name} and,
                        when they promise to pay, the amount and date to chase.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...logCollectionContact.form(loan.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                    className="flex min-h-0 flex-1 flex-col"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="min-h-0 flex-1 space-y-3 overflow-y-auto px-6 pb-6">
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="collection-channel">
                                            Channel
                                        </Label>

                                        <Select
                                            value={channel}
                                            onValueChange={(value) =>
                                                setChannel(
                                                    value as CollectionChannel,
                                                )
                                            }
                                        >
                                            <SelectTrigger
                                                id="collection-channel"
                                                className="w-full"
                                            >
                                                <SelectValue placeholder="Select a channel">
                                                    {selectedChannel?.label}
                                                </SelectValue>
                                            </SelectTrigger>

                                            <SelectContent>
                                                {channels.map((option) => (
                                                    <SelectItem
                                                        key={option.value}
                                                        value={option.value}
                                                    >
                                                        {option.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>

                                        <input
                                            type="hidden"
                                            name="channel"
                                            value={channel}
                                        />

                                        <InputError message={errors.channel} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="collection-outcome">
                                            Outcome
                                        </Label>

                                        <Select
                                            value={outcome}
                                            onValueChange={(value) =>
                                                setOutcome(
                                                    value as CollectionOutcome,
                                                )
                                            }
                                        >
                                            <SelectTrigger
                                                id="collection-outcome"
                                                className="w-full"
                                            >
                                                <SelectValue placeholder="Select an outcome">
                                                    {selectedOutcome?.label}
                                                </SelectValue>
                                            </SelectTrigger>

                                            <SelectContent>
                                                {outcomes.map((option) => (
                                                    <SelectItem
                                                        key={option.value}
                                                        value={option.value}
                                                    >
                                                        {option.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>

                                        <input
                                            type="hidden"
                                            name="outcome"
                                            value={outcome}
                                        />

                                        <InputError message={errors.outcome} />
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="collection-contacted-at">
                                        Contacted at
                                    </Label>

                                    <Input
                                        id="collection-contacted-at"
                                        name="contacted_at"
                                        type="datetime-local"
                                        defaultValue={currentLocalDateTime()}
                                    />

                                    <InputError message={errors.contacted_at} />
                                </div>

                                {isPromise && (
                                    <div className="grid gap-3 rounded-lg border bg-muted/30 p-3 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="collection-promised-amount">
                                                Promised amount
                                            </Label>

                                            <Input
                                                id="collection-promised-amount"
                                                name="promised_amount"
                                                type="number"
                                                step="0.01"
                                                min="0.01"
                                                defaultValue={Number(
                                                    outstanding,
                                                ).toFixed(2)}
                                                required
                                            />

                                            <InputError
                                                message={errors.promised_amount}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="collection-promised-at">
                                                Promise date
                                            </Label>

                                            <Input
                                                id="collection-promised-at"
                                                name="promised_at"
                                                type="date"
                                                required
                                            />

                                            <InputError
                                                message={errors.promised_at}
                                            />
                                        </div>

                                        <p className="text-xs text-muted-foreground sm:col-span-2">
                                            The promise is shown on the loan
                                            file until the date passes.
                                        </p>
                                    </div>
                                )}

                                <div className="grid gap-2">
                                    <Label htmlFor="collection-follow-up">
                                        Follow up on
                                    </Label>

                                    <Input
                                        id="collection-follow-up"
                                        name="follow_up_at"
                                        type="date"
                                    />

                                    <p className="text-xs text-muted-foreground">
                                        Optional. Defaults to the promise date.
                                        Leave blank when no follow-up is needed.
                                    </p>

                                    <InputError message={errors.follow_up_at} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="collection-notes">
                                        Notes
                                    </Label>

                                    <textarea
                                        id="collection-notes"
                                        name="notes"
                                        className={textareaClassName}
                                        placeholder="What was discussed, and what happens next"
                                    />

                                    <InputError message={errors.notes} />
                                </div>

                                <p className="text-xs text-muted-foreground">
                                    Outstanding balance{' '}
                                    <span className="font-medium text-foreground tabular-nums">
                                        {formatMoney(outstanding)}
                                    </span>
                                </p>
                            </div>

                            <DialogFooter className="border-t bg-background px-6 py-4">
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        Cancel
                                    </Button>
                                </DialogClose>

                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-test="confirm-log-contact-button"
                                >
                                    Log contact
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
