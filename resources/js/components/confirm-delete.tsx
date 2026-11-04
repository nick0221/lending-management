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

type ConfirmActionProps = {
    form: RouteFormDefinition<'post'>;
    title: string;
    description: string;
    label: string;
    processingLabel: string;
    variant?: 'default' | 'destructive';
    testId?: string;
};

/**
 * The confirmation body, controlled by the caller so row menus can open it
 * from a dropdown item.
 */
export function ConfirmActionDialog({
    form,
    title,
    description,
    label,
    processingLabel,
    variant = 'destructive',
    open,
    onOpenChange,
}: Omit<ConfirmActionProps, 'testId'> & {
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
                                variant={variant}
                                disabled={processing}
                            >
                                {processing ? processingLabel : label}
                            </Button>
                        )}
                    </Form>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

/**
 * Delete confirmation with copy tailored to permanent deletion.
 */
export function ConfirmDeleteDialog({
    form,
    title,
    description,
    open,
    onOpenChange,
}: Pick<ConfirmActionProps, 'form' | 'title' | 'description'> & {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <ConfirmActionDialog
            form={form}
            title={title}
            description={description}
            label="Delete"
            processingLabel="Deleting…"
            open={open}
            onOpenChange={onOpenChange}
        />
    );
}

type ConfirmDeleteProps = {
    form: RouteFormDefinition<'post'>;
    title: string;
    description: string;
    label?: string;
    testId?: string;
};

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
