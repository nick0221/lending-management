import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import { useCurrencySymbol } from '@/hooks/use-currency-symbol';
import type { BreadcrumbItem } from '@/types';

export default function AppLayout({
    breadcrumbs = [],
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    children: React.ReactNode;
}) {
    useCurrencySymbol();

    return (
        <AppLayoutTemplate breadcrumbs={breadcrumbs}>
            {children}
        </AppLayoutTemplate>
    );
}
