import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    FileBarChart,
    FolderGit2,
    HandCoins,
    Landmark,
    LayoutGrid,
    ShieldCheck,
    Users,
    Wallet,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as adminBorrowers } from '@/routes/admin/borrowers';
import { index as adminLoanProducts } from '@/routes/admin/loan-products';
import { index as adminLoans } from '@/routes/admin/loans';
import { index as adminReports } from '@/routes/admin/reports';
import { index as adminUsers } from '@/routes/admin/users';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

const adminNavItems: NavItem[] = [
    {
        title: 'Overview',
        href: adminDashboard(),
        icon: ShieldCheck,
    },
    {
        title: 'Users',
        href: adminUsers(),
        icon: Users,
    },
    {
        title: 'Borrowers',
        href: adminBorrowers(),
        icon: HandCoins,
    },
    {
        title: 'Loan products',
        href: adminLoanProducts(),
        icon: Landmark,
    },
    {
        title: 'Loans',
        href: adminLoans(),
        icon: Wallet,
    },
    {
        title: 'Reports',
        href: adminReports(),
        icon: FileBarChart,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { auth } = usePage().props;
    const canAccessAdmin =
        auth.user?.role === 'admin' || auth.user?.role === 'super_admin';

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />

                {canAccessAdmin && (
                    <NavMain items={adminNavItems} label="Administration" />
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
