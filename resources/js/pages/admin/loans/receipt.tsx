import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Loader2, Mail, Printer } from 'lucide-react';
import { useState } from 'react';
import { PaymentReceiptDocument } from '@/components/admin/payment-receipt-document';
import { Button } from '@/components/ui/button';
import { show as showLoan } from '@/routes/admin/loans';
import { email as emailReceipt } from '@/routes/admin/loans/payments/receipt';
import type { PaymentReceipt } from '@/types';

export default function PaymentReceiptPage({
    organisation,
    receipt,
}: {
    organisation: string;
    receipt: PaymentReceipt;
}) {
    const [processing, setProcessing] = useState(false);

    const email = (): void => {
        setProcessing(true);
        router.post(
            emailReceipt.url({
                loan: receipt.loan_id,
                payment: receipt.id,
            }),
            {},
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <>
            <Head title={`Receipt ${receipt.number}`} />

            <div className="min-h-svh bg-muted/40 p-4 print:bg-white print:p-0">
                <div className="mx-auto max-w-3xl space-y-4 print:max-w-none">
                    <div className="flex flex-wrap items-center justify-between gap-3 print:hidden">
                        <Button variant="ghost" asChild>
                            <Link href={showLoan(receipt.loan_id)}>
                                <ArrowLeft /> Back to loan
                            </Link>
                        </Button>

                        <div className="flex flex-wrap gap-2">
                            <Button
                                variant="outline"
                                onClick={() => window.print()}
                            >
                                <Printer /> Print
                            </Button>

                            <Button onClick={email} disabled={processing}>
                                {processing ? (
                                    <Loader2 className="animate-spin" />
                                ) : (
                                    <Mail />
                                )}
                                Email receipt
                            </Button>
                        </div>
                    </div>

                    <PaymentReceiptDocument
                        organisation={organisation}
                        receipt={receipt}
                        className="print:rounded-none print:border-0 print:bg-white print:p-0 print:text-black print:shadow-none"
                    />
                </div>
            </div>
        </>
    );
}
