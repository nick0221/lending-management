import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    ChartPie,
    CircleDollarSign,
    FileBarChart,
    FolderGit2,
    Gauge,
    HandCoins,
    LayoutGrid,
    ShieldCheck,
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
import { index as adminActivity } from '@/routes/admin/activity';
import { index as adminBorrowers } from '@/routes/admin/borrowers';
import { index as adminCollections } from '@/routes/admin/collections';
import { index as adminDisbursements } from '@/routes/admin/disbursements';
import { index as adminLoanProducts } from '@/routes/admin/loan-products';
import { index as adminLoans } from '@/routes/admin/loans';
import { index as adminNotifications } from '@/routes/admin/notifications';
import { index as adminPayments } from '@/routes/admin/payments';
import { index as adminReports } from '@/routes/admin/reports';
import { edit as adminSettings } from '@/routes/admin/settings';
import { index as adminSystem } from '@/routes/admin/system';
import { index as adminUsers } from '@/routes/admin/users';
import type { NavEntry, NavGroupItem, NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

const adminNavEntries: NavEntry[] = [
    {
        title: 'Overview',
        href: adminDashboard(),
        icon: Gauge,
    },
    {
        title: 'Lending',
        icon: HandCoins,
        items: [
            {
                title: 'Borrowers',
                href: adminBorrowers(),
            },
            {
                title: 'Loan products',
                href: adminLoanProducts(),
            },
            {
                title: 'Loans',
                href: adminLoans(),
            },
        ],
    },
    {
        title: 'Finance',
        icon: CircleDollarSign,
        items: [
            {
                title: 'Payments',
                href: adminPayments(),
            },
            {
                title: 'Disbursements',
                href: adminDisbursements(),
            },
            {
                title: 'Collections',
                href: adminCollections(),
            },
        ],
    },
    {
        title: 'Insights',
        icon: ChartPie,
        items: [
            {
                title: 'Reports',
                href: adminReports(),
            },
            {
                title: 'Notifications',
                href: adminNotifications(),
            },
        ],
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
    const isSuperAdmin = auth.user?.role === 'super_admin';

    const administrationNavItems: NavGroupItem = {
        title: 'Administration',
        icon: ShieldCheck,
        items: [
            {
                title: 'Users',
                href: adminUsers(),
            },
            ...(isSuperAdmin
                ? [
                      {
                          title: 'Activity log',
                          href: adminActivity(),
                      },
                      {
                          title: 'System status',
                          href: adminSystem(),
                      },
                      {
                          title: 'Settings',
                          href: adminSettings(),
                      },
                  ]
                : []),
        ],
    };

    const navigationItems: NavEntry[] = [
        ...adminNavEntries,
        administrationNavItems,
    ];

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
                    <NavMain items={navigationItems} label="Management" />
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
