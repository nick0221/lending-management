import { Head, Link, usePage } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { RoleBadge } from '@/components/role-badge';
import { Button } from '@/components/ui/button';
import { dashboard as adminDashboard } from '@/routes/admin';
import { dashboard } from '@/routes';

export default function Dashboard() {
    const { auth } = usePage().props;
    const canAccessAdmin =
        auth.user?.role === 'admin' || auth.user?.role === 'super_admin';

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                    <div className="space-y-1">
                        <div className="flex items-center gap-3">
                            <h1 className="text-xl font-semibold tracking-tight">
                                Welcome, {auth.user.name}
                            </h1>

                            {auth.user.role && (
                                <RoleBadge role={auth.user.role} />
                            )}
                        </div>

                        <p className="text-sm text-muted-foreground">
                            You are signed in with the access level assigned to
                            your account.
                        </p>
                    </div>

                    {canAccessAdmin && (
                        <Button asChild>
                            <Link href={adminDashboard()} prefetch>
                                <ShieldCheck />
                                Administration
                            </Link>
                        </Button>
                    )}
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
