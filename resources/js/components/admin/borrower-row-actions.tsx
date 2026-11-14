import { Link } from '@inertiajs/react';
import { Archive, Eye, MoreHorizontal, Pencil } from 'lucide-react';
import { useState } from 'react';
import { ConfirmActionDialog } from '@/components/confirm-delete';
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
 * Row menu for the borrower register: open the file, edit or remove.
 *
 * Removing archives a borrower who has no loan history; borrowers with loans
 * stay put so their payments and schedules can never be erased.
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
                                <Archive />
                                Archive
                            </DropdownMenuItem>
                        </>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>

            <ConfirmActionDialog
                form={destroyBorrower.form(borrower.id)}
                title={`Archive ${borrower.name}?`}
                description="The borrower leaves the working register but their file is kept, so nothing is lost if this was a mistake. Borrowers with loan history cannot be archived."
                label="Archive borrower"
                processingLabel="Archiving…"
                open={confirmOpen}
                onOpenChange={setConfirmOpen}
            />
        </>
    );
}
