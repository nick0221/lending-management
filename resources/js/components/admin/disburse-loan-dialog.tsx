import { Form } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import { formatDateOnly, formatMoney } from '@/lib/format';
import { disburse as disburseLoan } from '@/routes/admin/loans';
import type { DisbursementMethodOption, Loan } from '@/types';

export function DisburseLoanDialog({
    loan,
    methods,
}: {
    loan: Loan;
    methods: DisbursementMethodOption[];
}) {
    const [open, setOpen] = useState(false);
    const [method, setMethod] = useState<string>(methods[0]?.value ?? '');
    const today = new Date().toISOString().slice(0, 10);
    const [amount, setAmount] = useState<string>(
        Number(loan.principal).toFixed(2),
    );
    const [disbursedAt, setDisbursedAt] = useState<string>(today);

    const amountChanged =
        Math.abs(Number(amount) - Number(loan.principal)) > 0.005;
    const dateChanged = disbursedAt !== today;

    const handleOpenChange = (nextOpen: boolean) => {
        setOpen(nextOpen);

        if (nextOpen) {
            setAmount(Number(loan.principal).toFixed(2));
            setDisbursedAt(today);
        }
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogTrigger asChild>
                <Button data-test="disburse-loan-button">Disburse</Button>
            </DialogTrigger>

            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Disburse loan</DialogTitle>
                    <DialogDescription>
                        Record the funds paid out to {loan.borrower_name}. This
                        cannot be undone.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...disburseLoan.form(loan.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="disburse-amount">Amount</Label>

                                <Input
                                    id="disburse-amount"
                                    name="amount"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    max={Number(loan.principal)}
                                    value={amount}
                                    onChange={(event) =>
                                        setAmount(event.target.value)
                                    }
                                    required
                                />

                                <InputError message={errors.amount} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="disburse-method">Method</Label>

                                <Select
                                    value={method}
                                    onValueChange={setMethod}
                                >
                                    <SelectTrigger
                                        id="disburse-method"
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
                                <Label htmlFor="disburse-reference">
                                    Reference
                                </Label>

                                <Input
                                    id="disburse-reference"
                                    name="reference"
                                    placeholder="Optional transaction reference"
                                />

                                <InputError message={errors.reference} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="disburse-date">
                                    Disbursed on
                                </Label>

                                <Input
                                    id="disburse-date"
                                    name="disbursed_at"
                                    type="date"
                                    value={disbursedAt}
                                    onChange={(event) =>
                                        setDisbursedAt(event.target.value)
                                    }
                                />

                                <InputError message={errors.disbursed_at} />
                            </div>

                            {(amountChanged || dateChanged) && (
                                <Alert className="border-amber-500/40 bg-amber-500/10">
                                    <TriangleAlert className="text-amber-600 dark:text-amber-400" />

                                    <AlertTitle>
                                        The schedule will be regenerated
                                    </AlertTitle>

                                    <AlertDescription>
                                        {amountChanged && (
                                            <p>
                                                The amount differs from the
                                                approved principal of{' '}
                                                {formatMoney(loan.principal)}.
                                            </p>
                                        )}

                                        {dateChanged && (
                                            <p>
                                                The schedule will start on{' '}
                                                {disbursedAt
                                                    ? formatDateOnly(
                                                          disbursedAt,
                                                      )
                                                    : 'the selected date'}{' '}
                                                instead of today.
                                            </p>
                                        )}

                                        <p>
                                            The approved repayment schedule will
                                            be replaced when you disburse.
                                        </p>
                                    </AlertDescription>
                                </Alert>
                            )}

                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        Cancel
                                    </Button>
                                </DialogClose>

                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-test="confirm-disburse-loan-button"
                                >
                                    Disburse loan
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
