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
 * Ask a second administrator to release a payment's allocations and restore
 * the outstanding balance. The payment stays live until the request is
 * approved.
 */
export function ReversePaymentDialog({
    loan,
    payment,
    open,
    onOpenChange,
    returnTo,
}: {
    loan: Pick<Loan, 'id' | 'status'>;
    payment: Pick<Payment, 'id' | 'amount' | 'payment_date'> | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    returnTo?: 'register';
}) {
    if (!payment) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Request payment reversal</DialogTitle>
                    <DialogDescription>
                        Ask a different administrator to reverse the{' '}
                        {formatMoney(payment.amount)} payment recorded on{' '}
                        {formatDateOnly(payment.payment_date)}. The payment
                        stays live and keeps its allocations
                        {loan.status === 'closed'
                            ? '; approving the request reopens the loan'
                            : ''}
                        .
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
                            {returnTo && (
                                <input
                                    type="hidden"
                                    name="redirect"
                                    value={returnTo}
                                />
                            )}

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
                                        errors.approval ??
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
                                    Send for approval
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
