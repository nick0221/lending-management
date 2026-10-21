import type { ReactNode } from 'react';
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDateOnly, formatMoney } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { PaymentReceipt } from '@/types';

/**
 * The receipt body shared by the payment sheet and the printable page.
 */
export function PaymentReceiptDocument({
    organisation,
    receipt,
    className,
}: {
    organisation: string;
    receipt: PaymentReceipt;
    className?: string;
}) {
    const totals = receipt.allocations.reduce(
        (sum, allocation) => ({
            principal: sum.principal + Number(allocation.principal_allocated),
            interest: sum.interest + Number(allocation.interest_allocated),
            fee: sum.fee + Number(allocation.fee_allocated),
            penalty: sum.penalty + Number(allocation.penalty_allocated),
        }),
        { principal: 0, interest: 0, fee: 0, penalty: 0 },
    );

    const allocatedTotal =
        totals.principal + totals.interest + totals.fee + totals.penalty;

    return (
        <div className={cn('rounded-xl border bg-card p-6 text-sm', className)}>
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="space-y-1">
                    <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        {organisation}
                    </p>
                    <h1 className="text-xl font-semibold">Payment receipt</h1>
                    <p className="text-muted-foreground">
                        Receipt {receipt.number}
                    </p>
                </div>

                <div className="text-right">
                    <p className="text-xs text-muted-foreground">
                        Amount received
                    </p>
                    <p className="text-2xl font-semibold tabular-nums">
                        {formatMoney(receipt.amount)}
                    </p>
                </div>
            </div>

            <dl className="mt-6 grid gap-4 border-t pt-4 sm:grid-cols-2">
                <ReceiptField
                    label="Received from"
                    value={receipt.borrower_name}
                />
                <ReceiptField
                    label="Payment date"
                    value={formatDateOnly(receipt.payment_date)}
                />
                <ReceiptField
                    label="Loan"
                    value={`#${receipt.loan_id} · ${receipt.product_name}`}
                />
                <ReceiptField label="Method" value={receipt.method_label} />

                {receipt.reference && (
                    <ReceiptField label="Reference" value={receipt.reference} />
                )}

                <ReceiptField
                    label="Recorded by"
                    value={receipt.recorded_by ?? '—'}
                />
            </dl>

            <div className="mt-6 space-y-2">
                <h2 className="font-medium">Applied to</h2>

                {receipt.allocations.length === 0 ? (
                    <p className="text-muted-foreground">
                        This payment has not been allocated to an installment.
                    </p>
                ) : (
                    <div className="overflow-hidden rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Installment</TableHead>
                                    <TableHead className="text-right">
                                        Principal
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Interest
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Fee
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Penalty
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Total
                                    </TableHead>
                                </TableRow>
                            </TableHeader>

                            <TableBody>
                                {receipt.allocations.map((allocation) => {
                                    const total =
                                        Number(allocation.principal_allocated) +
                                        Number(allocation.interest_allocated) +
                                        Number(allocation.fee_allocated) +
                                        Number(allocation.penalty_allocated);

                                    return (
                                        <TableRow
                                            key={`${allocation.installment_sequence}-${allocation.principal_allocated}`}
                                        >
                                            <TableCell className="font-medium">
                                                #
                                                {allocation.installment_sequence ??
                                                    '—'}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {formatMoney(
                                                    allocation.principal_allocated,
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {formatMoney(
                                                    allocation.interest_allocated,
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {formatMoney(
                                                    allocation.fee_allocated,
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {formatMoney(
                                                    allocation.penalty_allocated,
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right font-medium tabular-nums">
                                                {formatMoney(total)}
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>

                            <TableFooter>
                                <TableRow>
                                    <TableCell className="font-medium">
                                        Total
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {formatMoney(totals.principal)}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {formatMoney(totals.interest)}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {formatMoney(totals.fee)}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {formatMoney(totals.penalty)}
                                    </TableCell>
                                    <TableCell className="text-right font-semibold tabular-nums">
                                        {formatMoney(allocatedTotal)}
                                    </TableCell>
                                </TableRow>
                            </TableFooter>
                        </Table>
                    </div>
                )}
            </div>

            {receipt.notes && (
                <p className="mt-4 text-muted-foreground">
                    <span className="font-medium text-foreground">Notes:</span>{' '}
                    {receipt.notes}
                </p>
            )}

            <p className="mt-6 border-t pt-3 text-xs text-muted-foreground">
                This receipt confirms the payment recorded against the loan
                above. Keep it for your records.
            </p>
        </div>
    );
}

function ReceiptField({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="space-y-0.5">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="font-medium break-words">{value}</dd>
        </div>
    );
}
