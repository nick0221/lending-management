import type { ReactNode } from 'react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';

/**
 * Card with a consistent section header. Content defaults to no padding so
 * tables can sit flush; pass `contentClassName="p-6"` for prose or forms.
 */
export function SectionCard({
    title,
    description,
    actions,
    children,
    className,
    contentClassName,
}: {
    title: string;
    description?: string;
    actions?: ReactNode;
    children: ReactNode;
    className?: string;
    contentClassName?: string;
}) {
    return (
        <Card className={cn('gap-0 py-0', className)}>
            <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-3 border-b py-4">
                <div className="space-y-0.5">
                    <CardTitle className="text-base">{title}</CardTitle>

                    {description && (
                        <CardDescription>{description}</CardDescription>
                    )}
                </div>

                {actions}
            </CardHeader>

            <CardContent className={cn('p-0', contentClassName)}>
                {children}
            </CardContent>
        </Card>
    );
}
