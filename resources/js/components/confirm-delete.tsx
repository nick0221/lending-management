import { Form } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import type { RouteFormDefinition } from '@/wayfinder';

type ConfirmDeleteProps = {
    form: RouteFormDefinition<'post'>;
    title: string;
    description: string;
    label?: string;
    testId?: string;
};

/**
 * The confirmation body, controlled by the caller so row menus can open it
 * from a dropdown item.
 */
export function ConfirmDeleteDialog({
    form,
    title,
    description,
    open,
    onOpenChange,
}: Omit<ConfirmDeleteProps, 'label' | 'testId'> & {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{title}</AlertDialogTitle>
                    <AlertDialogDescription>
                        {description}
                    </AlertDialogDescription>
                </AlertDialogHeader>

                <AlertDialogFooter>
                    <AlertDialogCancel>Cancel</AlertDialogCancel>

                    <Form {...form} options={{ preserveScroll: true }}>
                        {({ processing }) => (
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={processing}
                            >
                                {processing ? 'Deleting…' : 'Delete'}
                            </Button>
                        )}
                    </Form>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

/**
 * Destructive action behind an AlertDialog. The Inertia form only submits once
 * the user confirms, so row-level deletes can no longer fire on a misclick.
 */
export function ConfirmDelete({
    form,
    title,
    description,
    label = 'Delete',
    testId,
}: ConfirmDeleteProps) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <Button
                variant="destructive"
                size="sm"
                onClick={() => setOpen(true)}
                data-test={testId}
            >
                <Trash2 />
                {label}
            </Button>

            <ConfirmDeleteDialog
                form={form}
                title={title}
                description={description}
                open={open}
                onOpenChange={setOpen}
            />
        </>
    );
}
