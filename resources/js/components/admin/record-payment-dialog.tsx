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
    DialogTrigger,
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
import { cn } from '@/lib/utils';
import { store as recordPayment } from '@/routes/admin/loans/payments';
import type {
    Loan,
    LoanInstallment,
    PaymentBreakdown,
    PaymentMethodOption,
} from '@/types';

type AllocationKey = 'penalty' | 'fee' | 'interest' | 'principal';

const allocationRows: { key: AllocationKey; label: string }[] = [
    { key: 'penalty', label: 'Penalty' },
    { key: 'fee', label: 'Fee' },
    { key: 'interest', label: 'Interest' },
    { key: 'principal', label: 'Principal' },
];

/**
 * Mirror RepaymentService: walk the installments oldest first and apply the
 * amount to penalty, fee, interest and principal in that order.
 */
function previewAllocation(amount: number, installments: LoanInstallment[]) {
    let remaining = Math.max(Math.round(amount * 100), 0);

    const cents = {
        penalty: 0,
        fee: 0,
        interest: 0,
        principal: 0,
    };

    for (const installment of installments) {
        if (remaining === 0) {
            break;
        }

        const buckets = [
            [
                'penalty',
                Math.round(Number(installment.penalty_outstanding) * 100),
            ],
            ['fee', Math.round(Number(installment.fee_outstanding) * 100)],
            [
                'interest',
                Math.round(Number(installment.interest_outstanding) * 100),
            ],
            [
                'principal',
                Math.round(Number(installment.principal_outstanding) * 100),
            ],
        ] as const;

        for (const [key, outstanding] of buckets) {
            const applied = Math.min(remaining, outstanding);

            cents[key] += applied;
            remaining -= applied;
        }
    }

    return {
        penalty: cents.penalty / 100,
        fee: cents.fee / 100,
        interest: cents.interest / 100,
        principal: cents.principal / 100,
        unallocated: remaining / 100,
    };
}

export function RecordPaymentDialog({
    loan,
    methods,
    outstanding,
    breakdown,
    installments,
}: {
    loan: Loan;
    methods: PaymentMethodOption[];
    outstanding: string;
    breakdown: PaymentBreakdown;
    installments: LoanInstallment[];
}) {
    const [open, setOpen] = useState(false);
    const [method, setMethod] = useState<string>(methods[0]?.value ?? '');
    const [amount, setAmount] = useState<string>(
        Number(outstanding).toFixed(2),
    );

    const outstandingAmount = Number(outstanding);
    const parsedAmount = Number(amount);
    const hasAmount = Number.isFinite(parsedAmount) && parsedAmount > 0;
    const exceedsBalance =
        hasAmount && parsedAmount - outstandingAmount > 0.005;

    const preview = hasAmount
        ? previewAllocation(parsedAmount, installments)
        : { penalty: 0, fee: 0, interest: 0, principal: 0, unallocated: 0 };

    const nextInstallment = installments.find(
        (installment) => Number(installment.outstanding) > 0,
    );

    const handleOpenChange = (nextOpen: boolean) => {
        setOpen(nextOpen);

        if (nextOpen) {
            setAmount(Number(outstanding).toFixed(2));
        }
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogTrigger asChild>
                <Button data-test="record-payment-button">
                    Record payment
                </Button>
            </DialogTrigger>

            <DialogContent
                className="flex flex-col gap-0 overflow-hidden p-0"
                style={{ maxHeight: 'calc(100dvh - 2rem)' }}
            >
                <DialogHeader className="p-6 pb-4">
                    <DialogTitle>Record payment</DialogTitle>
                    <DialogDescription>
                        Record the money received from {loan.borrower_name}. It
                        is allocated to the oldest installments first.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...recordPayment.form(loan.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="flex min-h-0 flex-1 flex-col"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="min-h-0 flex-1 space-y-3 overflow-y-auto px-6 pb-6">
                                <div className="rounded-lg border bg-muted/30 p-3">
                                    <div className="flex items-baseline justify-between gap-3">
                                        <span className="text-xs text-muted-foreground uppercase">
                                            Outstanding balance
                                        </span>
                                        <span className="text-lg font-semibold tabular-nums">
                                            {formatMoney(outstanding)}
                                        </span>
                                    </div>

                                    <dl className="mt-2 grid grid-cols-2 gap-x-4 gap-y-1 text-xs sm:grid-cols-4">
                                        {allocationRows.map((row) => (
                                            <div key={row.key}>
                                                <dt className="text-muted-foreground">
                                                    {row.label}
                                                </dt>
                                                <dd className="font-medium tabular-nums">
                                                    {formatMoney(
                                                        breakdown[row.key],
                                                    )}
                                                </dd>
                                            </div>
                                        ))}
                                    </dl>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="payment-amount">
                                        Amount
                                    </Label>

                                    <Input
                                        id="payment-amount"
                                        name="amount"
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        max={outstandingAmount}
                                        value={amount}
                                        onChange={(event) =>
                                            setAmount(event.target.value)
                                        }
                                        required
                                    />

                                    <div className="flex flex-wrap gap-2">
                                        {nextInstallment && (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                className="h-7 px-2 text-xs"
                                                onClick={() =>
                                                    setAmount(
                                                        Number(
                                                            nextInstallment.outstanding,
                                                        ).toFixed(2),
                                                    )
                                                }
                                            >
                                                Next installment ·{' '}
                                                {formatMoney(
                                                    nextInstallment.outstanding,
                                                )}
                                            </Button>
                                        )}

                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="h-7 px-2 text-xs"
                                            onClick={() =>
                                                setAmount(
                                                    Number(outstanding).toFixed(
                                                        2,
                                                    ),
                                                )
                                            }
                                        >
                                            Full balance ·{' '}
                                            {formatMoney(outstanding)}
                                        </Button>
                                    </div>

                                    <InputError message={errors.amount} />

                                    {exceedsBalance && (
                                        <p className="text-xs text-destructive">
                                            The amount exceeds the outstanding
                                            balance.
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2 rounded-lg border bg-muted/30 p-3">
                                    <div className="flex flex-wrap items-baseline justify-between gap-2 text-xs">
                                        <span className="text-muted-foreground uppercase">
                                            Allocation preview
                                        </span>
                                        <span className="text-muted-foreground">
                                            Penalty → fee → interest → principal
                                        </span>
                                    </div>

                                    <dl className="grid grid-cols-2 gap-x-4 gap-y-1 sm:grid-cols-4">
                                        {allocationRows.map((row) => (
                                            <div key={row.key}>
                                                <dt className="text-xs text-muted-foreground">
                                                    {row.label}
                                                </dt>
                                                <dd
                                                    className={cn(
                                                        'text-sm font-medium tabular-nums',
                                                        preview[row.key] ===
                                                            0 &&
                                                            'text-muted-foreground',
                                                    )}
                                                >
                                                    {formatMoney(
                                                        preview[row.key],
                                                    )}
                                                </dd>
                                            </div>
                                        ))}
                                    </dl>

                                    {preview.unallocated > 0.005 && (
                                        <p className="text-xs text-destructive">
                                            {formatMoney(preview.unallocated)}{' '}
                                            would be unallocated.
                                        </p>
                                    )}
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="payment-method">
                                        Method
                                    </Label>

                                    <Select
                                        value={method}
                                        onValueChange={setMethod}
                                    >
                                        <SelectTrigger
                                            id="payment-method"
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="Select a method" />
                                        </SelectTrigger>

                                        <SelectContent>
                                            {methods.map((option) => (
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
                                        name="method"
                                        value={method}
                                    />

                                    <InputError message={errors.method} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="payment-reference">
                                        Reference
                                    </Label>

                                    <Input
                                        id="payment-reference"
                                        name="reference"
                                        placeholder="Optional transaction reference"
                                    />

                                    <InputError message={errors.reference} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="payment-date">
                                        Received on
                                    </Label>

                                    <Input
                                        id="payment-date"
                                        name="payment_date"
                                        type="date"
                                        defaultValue={new Date()
                                            .toISOString()
                                            .slice(0, 10)}
                                    />

                                    <InputError message={errors.payment_date} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="payment-notes">Notes</Label>

                                    <Input
                                        id="payment-notes"
                                        name="notes"
                                        placeholder="Optional notes"
                                    />

                                    <InputError message={errors.notes} />
                                </div>
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
                                    data-test="confirm-record-payment-button"
                                >
                                    Record payment
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
