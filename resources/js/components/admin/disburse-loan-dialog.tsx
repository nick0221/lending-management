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

    return (
        <Dialog open={open} onOpenChange={setOpen}>
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
                                    defaultValue={Number(
                                        loan.principal,
                                    ).toFixed(2)}
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
                                    defaultValue={new Date()
                                        .toISOString()
                                        .slice(0, 10)}
                                />

                                <InputError message={errors.disbursed_at} />
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
