import { Link, router } from '@inertiajs/react';
import { Eye, Mail, MoreHorizontal, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDeleteDialog } from '@/components/confirm-delete';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    destroy as destroyLoan,
    show as showLoan,
    statement as emailStatement,
} from '@/routes/admin/loans';
import type { LoanListItem } from '@/types';

/**
 * Row menu for the loan queue: view the file, email a statement or delete.
 */
export function LoanRowActions({ loan }: { loan: LoanListItem }) {
    const [confirmOpen, setConfirmOpen] = useState(false);

    if (!loan.can.view && !loan.can.delete) {
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
                            onSelect={() =>
                                router.post(
                                    emailStatement.url(loan.id),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <Mail />
                            Email statement
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
        </>
    );
}
