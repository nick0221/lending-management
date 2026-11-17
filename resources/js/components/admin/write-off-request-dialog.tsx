import { Form } from '@inertiajs/react';
import { Loader2, Send } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { formatMoney } from '@/lib/format';
import { writeOff as requestWriteOff } from '@/routes/admin/loans';
import type { Loan } from '@/types';

const textareaClassName =
    'border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 flex min-h-20 w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] md:text-sm';

/**
 * Ask a second administrator to write the loan off. The loan stays on the
 * active book until somebody else approves the request.
 */
export function WriteOffRequestDialog({
    loan,
    outstanding,
    open,
    onOpenChange,
}: {
    loan: Loan;
    outstanding: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Request write-off</DialogTitle>
                    <DialogDescription>
                        Write off the {formatMoney(outstanding)} outstanding
                        balance for {loan.borrower_name}. A different
                        administrator has to approve the request before the loan
                        is written off.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...requestWriteOff.form(loan.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="write-off-reason">Reason</Label>

                                <textarea
                                    id="write-off-reason"
                                    name="reason"
                                    required
                                    maxLength={500}
                                    placeholder="Explain why this balance cannot be collected."
                                    className={textareaClassName}
                                />

                                <InputError
                                    message={errors.reason ?? errors.approval}
                                />
                            </div>

                            <DialogFooter className="mt-4">
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        Cancel
                                    </Button>
                                </DialogClose>

                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={processing}
                                    data-test="request-write-off-button"
                                >
                                    {processing ? (
                                        <Loader2 className="animate-spin" />
                                    ) : (
                                        <Send />
                                    )}
                                    Send for approval
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
