import type { LucideIcon } from 'lucide-react';
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
}: {
    title: string;
    value: string | number;
    hint?: string;
    icon?: LucideIcon;
    tone?: keyof typeof tones;
}) {
    return (
        <Card>
            <CardHeader className="flex flex-row items-start justify-between space-y-0">
                <div className="space-y-1">
                    <CardDescription>{title}</CardDescription>
                    <CardTitle
                        className={cn('text-2xl tabular-nums', tones[tone])}
                    >
                        {value}
                    </CardTitle>
                </div>

                {Icon && (
                    <Icon className="size-4 shrink-0 text-muted-foreground" />
                )}
            </CardHeader>

            {hint && (
                <CardContent className="pt-0 text-xs text-muted-foreground">
                    {hint}
                </CardContent>
            )}
        </Card>
    );
}
