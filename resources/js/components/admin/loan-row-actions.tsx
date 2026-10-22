import { Link } from '@inertiajs/react';
import {
    Download,
    Eye,
    Mail,
    MoreHorizontal,
    Printer,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { StatementPreviewDialog } from '@/components/admin/statement-preview-dialog';
import { ConfirmDeleteDialog } from '@/components/confirm-delete';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { destroy as destroyLoan, show as showLoan } from '@/routes/admin/loans';
import {
    download as downloadStatement,
    print as printStatement,
} from '@/routes/admin/loans/statement';
import type { LoanListItem } from '@/types';

/**
 * Row menu for the loan queue: view the file, email a statement or delete.
 */
export function LoanRowActions({ loan }: { loan: LoanListItem }) {
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [statementOpen, setStatementOpen] = useState(false);

    if (
        !loan.can.view &&
        !loan.can.delete &&
        !loan.can.statement &&
        !loan.can.download_statement
    ) {
        return null;
    }

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`Actions for loan #${loan.id}`}
                    >
                        <MoreHorizontal />
                    </Button>
                </DropdownMenuTrigger>

                <DropdownMenuContent align="end" className="w-44">
                    {loan.can.view && (
                        <DropdownMenuItem asChild>
                            <Link href={showLoan(loan.id)} prefetch>
                                <Eye />
                                View
                            </Link>
                        </DropdownMenuItem>
                    )}

                    {loan.can.statement && (
                        <DropdownMenuItem
                            onSelect={() => setStatementOpen(true)}
                        >
                            <Mail />
                            Email statement
                        </DropdownMenuItem>
                    )}

                    {loan.can.download_statement && (
                        <DropdownMenuItem asChild>
                            <a href={downloadStatement.url(loan.id)}>
                                <Download />
                                Download statement
                            </a>
                        </DropdownMenuItem>
                    )}

                    {loan.can.download_statement && (
                        <DropdownMenuItem asChild>
                            <a
                                href={printStatement.url(loan.id)}
                                target="_blank"
                                rel="noreferrer"
                                data-test="print-statement-button"
                            >
                                <Printer />
                                Print statement
                            </a>
                        </DropdownMenuItem>
                    )}

                    {loan.can.delete && (
                        <>
                            <DropdownMenuSeparator />

                            <DropdownMenuItem
                                variant="destructive"
                                onSelect={(event) => {
                                    event.preventDefault();
                                    setConfirmOpen(true);
                                }}
                            >
                                <Trash2 />
                                Delete
                            </DropdownMenuItem>
                        </>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>

            <ConfirmDeleteDialog
                form={destroyLoan.form(loan.id)}
                title={`Delete loan #${loan.id}?`}
                description={`This permanently deletes the loan for ${loan.borrower_name}, including its schedule, payments and audit trail. This cannot be undone.`}
                open={confirmOpen}
                onOpenChange={setConfirmOpen}
            />

            {loan.can.statement && (
                <StatementPreviewDialog
                    loanId={loan.id}
                    borrowerName={loan.borrower_name}
                    open={statementOpen}
                    onOpenChange={setStatementOpen}
                />
            )}
        </>
    );
}
