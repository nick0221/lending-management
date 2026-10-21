import { router } from '@inertiajs/react';
import { Loader2, Mail, Printer } from 'lucide-react';
import { useState } from 'react';
import { PaymentReceiptDocument } from '@/components/admin/payment-receipt-document';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { receipt as receiptRoute } from '@/routes/admin/loans/payments';
import { email as emailReceipt } from '@/routes/admin/loans/payments/receipt';
import type { Loan, Payment, PaymentReceipt } from '@/types';

/**
 * Receipt preview for a payment row, with print and re-send actions.
 */
export function PaymentReceiptSheet({
    loan,
    payment,
    organisation,
    open,
    onOpenChange,
}: {
    loan: Loan;
    payment: Payment | null;
    organisation: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [processing, setProcessing] = useState(false);

    const receipt: PaymentReceipt | null = payment
        ? {
              id: payment.id,
              number: `R-${String(payment.id).padStart(6, '0')}`,
              payment_date: payment.payment_date,
              amount: payment.amount,
              method_label: payment.method_label,
              reference: payment.reference,
              notes: payment.notes,
              recorded_by: payment.recorded_by,
              reversed_at: payment.reversed_at,
              reversal_reason: payment.reversal_reason,
              reversed_by: payment.reversed_by,
              borrower_name: loan.borrower_name,
              borrower_email: null,
              loan_id: loan.id,
              product_name: loan.loan_product_name,
              allocations: payment.allocations,
          }
        : null;

    const email = (): void => {
        if (!payment) {
            return;
        }

        setProcessing(true);
        router.post(
            emailReceipt.url({ loan: loan.id, payment: payment.id }),
            {},
            {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="w-full gap-0 sm:max-w-xl">
                <SheetHeader className="border-b">
                    <SheetTitle>Payment receipt</SheetTitle>
                    <SheetDescription>
                        {receipt
                            ? `Receipt ${receipt.number} for loan #${loan.id}.`
                            : `Receipt for loan #${loan.id}.`}
                    </SheetDescription>
                </SheetHeader>

                {receipt && (
                    <div className="flex-1 overflow-y-auto p-4">
                        <PaymentReceiptDocument
                            organisation={organisation}
                            receipt={receipt}
                            className="border-0 p-0 shadow-none"
                        />
                    </div>
                )}

                <SheetFooter className="flex-row gap-2 border-t">
                    {receipt && payment && (
                        <>
                            <Button
                                variant="outline"
                                asChild
                                className="flex-1"
                            >
                                <a
                                    href={
                                        receiptRoute({
                                            loan: loan.id,
                                            payment: payment.id,
                                        }).url
                                    }
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <Printer /> Print
                                </a>
                            </Button>

                            {!payment.reversed_at && (
                                <Button
                                    className="flex-1"
                                    onClick={email}
                                    disabled={processing}
                                >
                                    {processing ? (
                                        <Loader2 className="animate-spin" />
                                    ) : (
                                        <Mail />
                                    )}
                                    Email receipt
                                </Button>
                            )}
                        </>
                    )}
                </SheetFooter>
            </SheetContent>
        </Sheet>
    );
}
