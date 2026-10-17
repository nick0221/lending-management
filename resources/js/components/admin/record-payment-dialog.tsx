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
import { store as recordPayment } from '@/routes/admin/loans/payments';
import type { Loan, PaymentMethodOption } from '@/types';

export function RecordPaymentDialog({
    loan,
    methods,
    outstanding,
}: {
    loan: Loan;
    methods: PaymentMethodOption[];
    outstanding: string;
}) {
    const [open, setOpen] = useState(false);
    const [method, setMethod] = useState<string>(methods[0]?.value ?? '');

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button data-test="record-payment-button">Record payment</Button>
            </DialogTrigger>

            <DialogContent>
                <DialogHeader>
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
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="payment-amount">Amount</Label>

                                <Input
                                    id="payment-amount"
                                    name="amount"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    max={Number(outstanding)}
                                    defaultValue={Number(outstanding).toFixed(2)}
                                    required
                                />

                                <InputError message={errors.amount} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="payment-method">Method</Label>

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
                                <Label htmlFor="payment-date">Received on</Label>

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

                            <DialogFooter>
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
