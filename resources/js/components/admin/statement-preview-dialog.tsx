import { router } from '@inertiajs/react';
import { Loader2, Mail } from 'lucide-react';
import { useEffect, useState } from 'react';
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
import { statement as loanStatement } from '@/routes/admin/loans';
import { preview as previewStatement } from '@/routes/admin/loans/statement';
import type { StatementPreview } from '@/types';

/**
 * Show the rendered statement email before staff send it. The preview is
 * fetched on open so the loan page does not render the markdown up front.
 */
export function StatementPreviewDialog({
    loanId,
    borrowerName,
    open,
    onOpenChange,
}: {
    loanId: number;
    borrowerName: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [preview, setPreview] = useState<StatementPreview | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [sending, setSending] = useState(false);

    useEffect(() => {
        if (!open) {
            return;
        }

        let cancelled = false;

        setLoading(true);
        setError(null);
        setPreview(null);

        fetch(previewStatement.url(loanId), {
            headers: { Accept: 'application/json' },
        })
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error(
                        'Unable to load the statement preview. Please try again.',
                    );
                }

                return (await response.json()) as StatementPreview;
            })
            .then((data) => {
                if (!cancelled) {
                    setPreview(data);
                }
            })
            .catch((reason: unknown) => {
                if (!cancelled) {
                    setError(
                        reason instanceof Error
                            ? reason.message
                            : 'Unable to load the statement preview.',
                    );
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setLoading(false);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [loanId, open]);

    const send = (): void => {
        setSending(true);

        router.post(
            loanStatement.url(loanId),
            {},
            {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
                onFinish: () => setSending(false),
            },
        );
    };

    const canSend = preview?.eligible === true && !loading && !sending;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>Email statement</DialogTitle>
                    <DialogDescription>
                        Preview the statement for {borrowerName} before it is
                        sent.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-3">
                    {loading && (
                        <div className="flex h-64 flex-col items-center justify-center gap-3 rounded-md border bg-muted/30 text-sm text-muted-foreground">
                            <Loader2 className="size-5 animate-spin" />
                            Loading the statement preview...
                        </div>
                    )}

                    {error && (
                        <p className="rounded-md border border-destructive/30 bg-destructive/10 p-4 text-sm text-destructive">
                            {error}
                        </p>
                    )}

                    {preview && !preview.eligible && (
                        <p className="rounded-md border bg-muted/40 p-4 text-sm text-muted-foreground">
                            {preview.message ??
                                'This borrower cannot receive statements.'}
                        </p>
                    )}

                    {preview?.eligible && (
                        <>
                            <dl className="grid gap-1 rounded-md border bg-muted/30 p-3 text-sm">
                                <div className="flex items-start justify-between gap-4">
                                    <dt className="text-muted-foreground">
                                        To
                                    </dt>
                                    <dd className="text-right font-medium">
                                        {preview.to}
                                    </dd>
                                </div>

                                <div className="flex items-start justify-between gap-4">
                                    <dt className="text-muted-foreground">
                                        Subject
                                    </dt>
                                    <dd className="text-right font-medium">
                                        {preview.subject}
                                    </dd>
                                </div>
                            </dl>

                            <iframe
                                title="Statement email preview"
                                srcDoc={preview.html ?? ''}
                                sandbox=""
                                className="h-[55vh] w-full rounded-md border bg-white"
                            />
                        </>
                    )}
                </div>

                <DialogFooter>
                    <DialogClose asChild>
                        <Button type="button" variant="secondary">
                            Cancel
                        </Button>
                    </DialogClose>

                    <Button
                        type="button"
                        onClick={send}
                        disabled={!canSend}
                        data-test="send-statement-button"
                    >
                        {sending ? (
                            <Loader2 className="animate-spin" />
                        ) : (
                            <Mail />
                        )}
                        Send statement
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
