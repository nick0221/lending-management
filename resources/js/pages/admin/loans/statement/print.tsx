import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useCurrencySymbol } from '@/hooks/use-currency-symbol';
import { formatDateOnly, formatMoney } from '@/lib/format';
import { show as showLoan } from '@/routes/admin/loans';
import type { Loan, LoanInstallment, Payment, PaymentTotals } from '@/types';

type StatementBorrower = {
    name: string;
    phone: string | null;
    email: string | null;
    national_id: string | null;
};

function Metric({ label, value }: { label: string; value: string }) {
    return (
        <div className="border-b pb-2">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="font-medium tabular-nums">{value}</dd>
        </div>
    );
}

function installmentStatus(installment: LoanInstallment): string {
    if (installment.overdue) {
        return 'Overdue';
    }

    return Number(installment.outstanding) > 0 ? 'Open' : 'Paid';
}

export default function LoanStatementPrint({
    organisation,
    generated_at,
    loan,
    borrower,
    installments,
    payments,
    totals,
}: {
    organisation: string;
    generated_at: string;
    loan: Loan;
    borrower: StatementBorrower;
    installments: LoanInstallment[];
    payments: Payment[];
    totals: PaymentTotals;
}) {
    useCurrencySymbol();

    const contact = [borrower.phone, borrower.email, borrower.national_id]
        .filter(Boolean)
        .join(' · ');

    return (
        <>
            <Head title={`Statement #${loan.id}`} />

            <div className="min-h-svh bg-muted/40 p-4 print:bg-white print:p-0">
                <div className="mx-auto max-w-4xl space-y-4 print:max-w-none">
                    <div className="flex flex-wrap items-center justify-between gap-3 print:hidden">
                        <Button variant="ghost" asChild>
                            <Link href={showLoan(loan.id)}>
                                <ArrowLeft /> Back to loan
                            </Link>
                        </Button>

                        <Button
                            variant="outline"
                            onClick={() => window.print()}
                        >
                            <Printer /> Print / Save as PDF
                        </Button>
                    </div>

                    <article className="rounded-xl border bg-card p-6 text-sm print:rounded-none print:border-0 print:bg-white print:p-0 print:text-black print:shadow-none print:[&_*]:bg-transparent print:[&_*]:text-black">
                        <header className="flex flex-wrap items-start justify-between gap-4">
                            <div className="space-y-1">
                                <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    {organisation}
                                </p>
                                <h1 className="text-xl font-semibold">
                                    Loan statement
                                </h1>
                                <p className="text-muted-foreground">
                                    Loan #{loan.id} · {loan.status_label}
                                </p>
                                <p className="text-muted-foreground">
                                    Generated {formatDateOnly(generated_at)}
                                </p>
                            </div>

                            <div className="text-right">
                                <p className="text-xs text-muted-foreground">
                                    Outstanding
                                </p>
                                <p className="text-2xl font-semibold tabular-nums">
                                    {formatMoney(totals.outstanding)}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Arrears {formatMoney(totals.arrears)}
                                </p>
                            </div>
                        </header>

                        <section className="mt-6 grid gap-6 sm:grid-cols-2">
                            <div className="space-y-1">
                                <h2 className="text-xs font-semibold tracking-wide uppercase">
                                    Borrower
                                </h2>
                                <p className="font-medium">{borrower.name}</p>
                                {contact && (
                                    <p className="text-muted-foreground">
                                        {contact}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-1 sm:text-right">
                                <h2 className="text-xs font-semibold tracking-wide uppercase">
                                    Loan
                                </h2>
                                <p className="font-medium">
                                    {loan.loan_product_name}
                                </p>
                                <p className="text-muted-foreground">
                                    {formatMoney(loan.principal)} ·{' '}
                                    {Number(loan.interest_rate).toFixed(2)}%{' '}
                                    {loan.interest_method_label}
                                </p>
                                <p className="text-muted-foreground">
                                    {loan.term_months} months ·{' '}
                                    {loan.repayment_frequency_label}
                                </p>
                                <p className="text-muted-foreground">
                                    Applied {formatDateOnly(loan.applied_at)}
                                    {loan.disbursed_at
                                        ? ` · Disbursed ${formatDateOnly(loan.disbursed_at)}`
                                        : ''}
                                </p>
                            </div>
                        </section>

                        <section className="mt-6 space-y-3">
                            <h2 className="text-xs font-semibold tracking-wide uppercase">
                                Summary
                            </h2>

                            <dl className="grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-5">
                                <Metric
                                    label="Principal"
                                    value={formatMoney(loan.principal)}
                                />
                                <Metric
                                    label="Total payable"
                                    value={formatMoney(totals.payable)}
                                />
                                <Metric
                                    label="Paid"
                                    value={formatMoney(totals.paid)}
                                />
                                <Metric
                                    label="Outstanding"
                                    value={formatMoney(totals.outstanding)}
                                />
                                <Metric
                                    label="Arrears"
                                    value={formatMoney(totals.arrears)}
                                />
                            </dl>
                        </section>

                        <section className="mt-6 space-y-3">
                            <h2 className="text-xs font-semibold tracking-wide uppercase">
                                Repayment schedule
                            </h2>

                            {installments.length === 0 ? (
                                <p className="text-muted-foreground">
                                    The schedule has not been generated yet.
                                </p>
                            ) : (
                                <Table className="print:text-[10px] print:[&_td]:px-2 print:[&_td]:py-1 print:[&_th]:h-7 print:[&_th]:px-2">
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="w-10">
                                                #
                                            </TableHead>
                                            <TableHead>Due date</TableHead>
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
                                                Paid
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Outstanding
                                            </TableHead>
                                            <TableHead>Status</TableHead>
                                        </TableRow>
                                    </TableHeader>

                                    <TableBody>
                                        {installments.map((installment) => (
                                            <TableRow
                                                key={installment.sequence}
                                            >
                                                <TableCell>
                                                    {installment.sequence}
                                                </TableCell>
                                                <TableCell>
                                                    {formatDateOnly(
                                                        installment.due_date,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {formatMoney(
                                                        installment.principal_due,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {formatMoney(
                                                        installment.interest_due,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {formatMoney(
                                                        installment.fee_due,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {formatMoney(
                                                        installment.penalty_accrued,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {formatMoney(
                                                        installment.paid,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {formatMoney(
                                                        installment.outstanding,
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    {installmentStatus(
                                                        installment,
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </section>

                        <section className="mt-6 space-y-3">
                            <h2 className="text-xs font-semibold tracking-wide uppercase">
                                Payments
                            </h2>

                            {payments.length === 0 ? (
                                <p className="text-muted-foreground">
                                    No payments have been recorded.
                                </p>
                            ) : (
                                <Table className="print:text-[10px] print:[&_td]:px-2 print:[&_td]:py-1 print:[&_th]:h-7 print:[&_th]:px-2">
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Date</TableHead>
                                            <TableHead>Method</TableHead>
                                            <TableHead>Reference</TableHead>
                                            <TableHead className="text-right">
                                                Amount
                                            </TableHead>
                                            <TableHead>Status</TableHead>
                                        </TableRow>
                                    </TableHeader>

                                    <TableBody>
                                        {payments.map((payment) => (
                                            <TableRow key={payment.id}>
                                                <TableCell>
                                                    {formatDateOnly(
                                                        payment.payment_date,
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    {payment.method_label}
                                                </TableCell>
                                                <TableCell>
                                                    {payment.reference || '-'}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {formatMoney(
                                                        payment.amount,
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    {payment.reversed_at
                                                        ? 'Reversed'
                                                        : 'Posted'}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </section>

                        <footer className="mt-6 border-t pt-4 text-xs text-muted-foreground">
                            Generated from the loan record on{' '}
                            {formatDateOnly(generated_at)}. Amounts are rounded
                            to two decimals.
                        </footer>
                    </article>
                </div>
            </div>
        </>
    );
}
