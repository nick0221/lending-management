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
import { restructure as restructureLoan } from '@/routes/admin/loans';
import type { Loan } from '@/types';

export function RestructureLoanDialog({
    loan,
    outstanding,
}: {
    loan: Loan;
    outstanding: string;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" data-test="restructure-loan-button">
                    Restructure
                </Button>
            </DialogTrigger>

            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Restructure loan</DialogTitle>
                    <DialogDescription>
                        Reschedule the outstanding principal of{' '}
                        {Number(outstanding).toFixed(2)} onto a fresh schedule.
                        Any accrued penalties and existing payments are kept.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...restructureLoan.form(loan.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="restructure-term">
                                    New term (months)
                                </Label>

                                <Input
                                    id="restructure-term"
                                    name="term_months"
                                    type="number"
                                    step="1"
                                    min="1"
                                    max="600"
                                    defaultValue={loan.term_months}
                                    required
                                />

                                <InputError message={errors.term_months} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="restructure-starts-on">
                                    First installment due
                                </Label>

                                <Input
                                    id="restructure-starts-on"
                                    name="starts_on"
                                    type="date"
                                    defaultValue={new Date()
                                        .toISOString()
                                        .slice(0, 10)}
                                />

                                <InputError message={errors.starts_on} />
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
                                    data-test="confirm-restructure-loan-button"
                                >
                                    Restructure loan
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
