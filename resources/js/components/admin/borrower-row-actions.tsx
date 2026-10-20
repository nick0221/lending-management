import { Link } from '@inertiajs/react';
import { Eye, MoreHorizontal, Pencil, Trash2 } from 'lucide-react';
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
    destroy as destroyBorrower,
    edit as editBorrower,
    show as showBorrower,
} from '@/routes/admin/borrowers';
import type { BorrowerListItem } from '@/types';

/**
 * Row menu for the borrower register: open the file, edit or delete.
 */
export function BorrowerRowActions({
    borrower,
}: {
    borrower: BorrowerListItem;
}) {
    const [confirmOpen, setConfirmOpen] = useState(false);

    if (!borrower.can.view && !borrower.can.update && !borrower.can.delete) {
        return null;
    }

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`Actions for ${borrower.name}`}
                    >
                        <MoreHorizontal />
                    </Button>
                </DropdownMenuTrigger>

                <DropdownMenuContent align="end" className="w-44">
                    {borrower.can.view && (
                        <DropdownMenuItem asChild>
                            <Link href={showBorrower(borrower.id)} prefetch>
                                <Eye />
                                View
                            </Link>
                        </DropdownMenuItem>
                    )}

                    {borrower.can.update && (
                        <DropdownMenuItem asChild>
                            <Link href={editBorrower(borrower.id)} prefetch>
                                <Pencil />
                                Edit
                            </Link>
                        </DropdownMenuItem>
                    )}

                    {borrower.can.delete && (
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
                form={destroyBorrower.form(borrower.id)}
                title={`Delete ${borrower.name}?`}
                description="This permanently deletes the borrower and every loan, payment and schedule attached to them. This cannot be undone."
                open={confirmOpen}
                onOpenChange={setConfirmOpen}
            />
        </>
    );
}
