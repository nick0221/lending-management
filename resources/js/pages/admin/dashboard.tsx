import { Head, Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as adminUsers } from '@/routes/admin/users';

type Stats = {
    total: number;
    superAdmins: number;
    admins: number;
    users: number;
};

export default function AdminDashboard({ stats }: { stats: Stats }) {
    const cards: { title: string; value: number }[] = [
        { title: 'Total users', value: stats.total },
        { title: 'Super admins', value: stats.superAdmins },
        { title: 'Admins', value: stats.admins },
        { title: 'Users', value: stats.users },
    ];

    return (
        <>
            <Head title="Administration" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        title="Administration"
                        description="Overview of the accounts and their access levels."
                    />

                    <Button asChild>
                        <Link href={adminUsers()} prefetch>
                            Manage users
                            <ArrowRight />
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {cards.map((card) => (
                        <Card key={card.title}>
                            <CardHeader>
                                <CardDescription>{card.title}</CardDescription>
                                <CardTitle className="text-2xl">
                                    {card.value}
                                </CardTitle>
                            </CardHeader>
                        </Card>
                    ))}
                </div>
            </div>
        </>
    );
}

AdminDashboard.layout = {
    breadcrumbs: [
        {
            title: 'Administration',
            href: adminDashboard(),
        },
    ],
};
