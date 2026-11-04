import { LoanStatement } from '@/components/loan-statement';
import type { StatementTotals } from '@/components/loan-statement';
import { show as showLoan } from '@/routes/loans';
import type { Loan, LoanInstallment, Payment } from '@/types';

type StatementBorrower = {
    name: string;
    phone: string | null;
    email: string | null;
    national_id: string | null;
};

export default function BorrowerLoanStatement({
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
    totals: StatementTotals;
}) {
    return (
        <LoanStatement
            organisation={organisation}
            generated_at={generated_at}
            loan={loan}
            borrower={borrower}
            installments={installments}
            payments={payments}
            totals={totals}
            backUrl={showLoan.url(loan.id)}
            backLabel="Back to my loan"
        />
    );
}
