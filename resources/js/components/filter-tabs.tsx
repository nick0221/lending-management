import { Link } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import type { ComponentProps } from 'react';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';

export type FilterTab = {
    value: string;
    label: string;
    href: ComponentProps<typeof Link>['href'];
    count?: number;
};

/**
 * URL-driven tabs for list filters. The active tab comes from the server, so
 * every filter stays bookmarkable and works without client-side state.
 */
export function FilterTabs({
    tabs,
    value,
    label = 'Filter',
    className,
}: {
    tabs: FilterTab[];
    value: string;
    label?: string;
    className?: string;
}) {
    const listRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        listRef.current
            ?.querySelector<HTMLElement>('[data-state="active"]')
            ?.scrollIntoView({ block: 'nearest', inline: 'center' });
    }, [value]);

    return (
        <Tabs value={value} className={className}>
            <TabsList
                ref={listRef}
                aria-label={label}
                className="h-auto max-w-full flex-nowrap justify-start overflow-x-auto sm:flex-wrap sm:overflow-visible"
            >
                {tabs.map((tab) => (
                    <TabsTrigger
                        key={tab.value}
                        value={tab.value}
                        asChild
                        className="h-7 flex-none px-3"
                    >
                        <Link href={tab.href} preserveScroll prefetch>
                            {tab.label}
                            {tab.count !== undefined && (
                                <span className="text-xs tabular-nums opacity-60">
                                    {tab.count}
                                </span>
                            )}
                        </Link>
                    </TabsTrigger>
                ))}
            </TabsList>
        </Tabs>
    );
}
