import type { ReactNode } from 'react';
import { SectionCard } from '@/components/section-card';

/**
 * Sticky aside for a create/edit form: a live summary followed by the
 * form's primary and secondary actions.
 */
export function FormSummary({
    title,
    description,
    children,
    footer,
}: {
    title: string;
    description?: string;
    children: ReactNode;
    footer: ReactNode;
}) {
    return (
        <SectionCard
            title={title}
            description={description}
            className="lg:sticky lg:top-6"
            contentClassName="space-y-4 p-4"
        >
            <dl className="space-y-2 text-sm">{children}</dl>

            <div className="space-y-2 border-t pt-4">{footer}</div>
        </SectionCard>
    );
}
