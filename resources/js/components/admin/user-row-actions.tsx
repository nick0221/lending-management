import { Link, router } from '@inertiajs/react';
import {
    KeyRound,
    MailCheck,
    MoreHorizontal,
    Pencil,
    Trash2,
} from 'lucide-react';
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
    destroy as destroyUser,
    edit as editUser,
    passwordReset,
    verification,
} from '@/routes/admin/users';
import type { AdminUserListItem } from '@/types';

/**
 * Row menu for the user register: edit the account, send account recovery
 * emails, or delete it after confirmation.
 */
export function UserRowActions({ user }: { user: AdminUserListItem }) {
    const [confirmOpen, setConfirmOpen] = useState(false);

    if (!user.can.update && !user.can.delete) {
        return null;
    }

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`Actions for ${user.name}`}
                    >
                        <MoreHorizontal />
                    </Button>
                </DropdownMenuTrigger>

                <DropdownMenuContent align="end" className="w-56">
                    {user.can.update && (
                        <DropdownMenuItem asChild>
                            <Link href={editUser(user.id)} prefetch>
                                <Pencil />
                                Edit account
                            </Link>
                        </DropdownMenuItem>
                    )}

                    {user.can.update && !user.email_verified_at && (
                        <DropdownMenuItem
                            onSelect={() =>
                                router.post(
                                    verification(user.id).url,
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <MailCheck />
                            Resend verification
                        </DropdownMenuItem>
                    )}

                    {user.can.update && (
                        <DropdownMenuItem
                            onSelect={() =>
                                router.post(
                                    passwordReset(user.id).url,
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <KeyRound />
                            Send password reset
                        </DropdownMenuItem>
                    )}

                    {user.can.delete && (
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
                                Delete account
                            </DropdownMenuItem>
                        </>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>

            <ConfirmDeleteDialog
                form={destroyUser.form(user.id)}
                title={`Delete ${user.name}?`}
                description="This permanently removes the account and revokes access. Any linked borrower profile and its loan history are kept."
                open={confirmOpen}
                onOpenChange={setConfirmOpen}
            />
        </>
    );
}
