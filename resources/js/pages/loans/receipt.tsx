import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import { PaymentReceiptDocument } from '@/components/payment-receipt-document';
import { Button } from '@/components/ui/button';
import { useCurrencySymbol } from '@/hooks/use-currency-symbol';
import { show as showLoan } from '@/routes/loans';
import type { PaymentReceipt } from '@/types';

export default function BorrowerReceipt({
    organisation,
    receipt,
}: {
    organisation: string;
    receipt: PaymentReceipt;
}) {
    useCurrencySymbol();

    return (
        <>
            <Head title={`Receipt ${receipt.number}`} />

            <div className="min-h-svh bg-muted/40 p-4 print:bg-white print:p-0">
                <div className="mx-auto max-w-3xl space-y-4 print:max-w-none">
                    <div className="flex flex-wrap items-center justify-between gap-3 print:hidden">
                        <Button variant="ghost" asChild>
                            <Link href={showLoan(receipt.loan_id)}>
                                <ArrowLeft /> Back to my loan
                            </Link>
                        </Button>

                        <Button
                            variant="outline"
                            onClick={() => window.print()}
                        >
                            <Printer /> Print / Save as PDF
                        </Button>
                    </div>

                    <PaymentReceiptDocument
                        organisation={organisation}
                        receipt={receipt}
                        className="print:rounded-none print:border-0 print:bg-white print:p-0 print:text-black print:shadow-none print:[&_*]:bg-transparent print:[&_*]:text-black"
                    />
                </div>
            </div>
        </>
    );
}
