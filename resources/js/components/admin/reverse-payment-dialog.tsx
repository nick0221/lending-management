import { Form } from '@inertiajs/react';
import { Loader2, Undo2 } from 'lucide-react';
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
import { Label } from '@/components/ui/label';
import { formatDateOnly, formatMoney } from '@/lib/format';
import { reverse as reversePayment } from '@/routes/admin/loans/payments';
import type { Loan, Payment } from '@/types';

const textareaClassName =
    'border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 flex min-h-20 w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] md:text-sm';

/**
 * Ask for a reason before releasing a payment's allocations and restoring the
 * outstanding balance.
 */
export function ReversePaymentDialog({
    loan,
    payment,
    open,
    onOpenChange,
}: {
    loan: Loan;
    payment: Payment | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    if (!payment) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Reverse payment</DialogTitle>
                    <DialogDescription>
                        Reverse the {formatMoney(payment.amount)} payment
                        recorded on {formatDateOnly(payment.payment_date)}. This
                        releases its allocations and restores the outstanding
                        balance
                        {loan.status === 'closed' ? ', reopening the loan' : ''}
                        . This cannot be undone.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...reversePayment.form({
                        loan: loan.id,
                        payment: payment.id,
                    })}
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="reversal-reason">Reason</Label>

                                <textarea
                                    id="reversal-reason"
                                    name="reason"
                                    required
                                    maxLength={500}
                                    placeholder="Explain why this payment is being reversed."
                                    className={textareaClassName}
                                />

                                <InputError
                                    message={
                                        errors.reason ??
                                        errors.payment ??
                                        errors.loan
                                    }
                                />
                            </div>

                            <DialogFooter className="mt-4">
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        Cancel
                                    </Button>
                                </DialogClose>

                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={processing}
                                    data-test="reverse-payment-button"
                                >
                                    {processing ? (
                                        <Loader2 className="animate-spin" />
                                    ) : (
                                        <Undo2 />
                                    )}
                                    Reverse payment
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
