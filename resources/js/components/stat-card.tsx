import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import type { ComponentProps } from 'react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';

const tones = {
    default: 'text-foreground',
    success: 'text-emerald-600 dark:text-emerald-400',
    warning: 'text-amber-600 dark:text-amber-400',
    danger: 'text-destructive',
} as const;

export default function StatCard({
    title,
    value,
    hint,
    icon: Icon,
    tone = 'default',
    href,
}: {
    title: string;
    value: string | number;
    hint?: string;
    icon?: LucideIcon;
    tone?: keyof typeof tones;
    href?: ComponentProps<typeof Link>['href'];
}) {
    const card = (
        <Card
            className={cn(
                'h-full gap-4 py-4 sm:gap-6 sm:py-6',
                href &&
                    'transition-colors hover:border-primary/40 hover:bg-accent/30',
            )}
        >
            <CardHeader className="flex flex-row items-start justify-between space-y-0 px-4 sm:px-6">
                <div className="space-y-1">
                    <CardDescription>{title}</CardDescription>
                    <CardTitle
                        className={cn(
                            'text-xl tabular-nums sm:text-2xl',
                            tones[tone],
                        )}
                    >
                        {value}
                    </CardTitle>
                </div>

                {Icon && (
                    <Icon className="size-4 shrink-0 text-muted-foreground" />
                )}
            </CardHeader>

            {hint && (
                <CardContent className="px-4 pt-0 text-xs text-muted-foreground sm:px-6">
                    {hint}
                </CardContent>
            )}
        </Card>
    );

    if (!href) {
        return card;
    }

    return (
        <Link
            href={href}
            prefetch
            className="block h-full rounded-xl outline-none focus-visible:ring-2 focus-visible:ring-ring"
        >
            {card}
        </Link>
    );
}
