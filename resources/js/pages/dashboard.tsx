import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ArrowRight, CircleDollarSign, FolderKanban, ReceiptText, Users } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

type Invoice = {
    id: number;
    status: 'unpaid' | 'partial' | 'paid';
    total: number;
    client?: string;
    project?: string;
    milestone?: string;
};

type Project = {
    id: number;
    name: string;
    client?: string;
    milestones_count: number;
};

type Props = {
    summary: {
        clients: number;
        projects: number;
        unpaid: number;
        paid_amount: number;
    };
    recentInvoices: Invoice[];
    recentProjects: Project[];
};

const currency = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'PHP',
});

export default function Dashboard({ summary, recentInvoices, recentProjects }: Props) {
    const cards = [
        { label: 'Total clients', value: summary.clients, icon: Users },
        { label: 'Active projects', value: summary.projects, icon: FolderKanban },
        { label: 'Invoices to collect', value: summary.unpaid, icon: ReceiptText },
        { label: 'Paid revenue', value: currency.format(summary.paid_amount), icon: CircleDollarSign },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Billing overview</h1>
                        <p className="mt-1 text-sm text-muted-foreground">See project work, invoice status, and revenue at a glance.</p>
                    </div>
                    <Button asChild>
                        <Link href="/create-project">
                            Create project
                            <ArrowRight className="size-4" />
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {cards.map(({ label, value, icon: Icon }) => (
                        <Card key={label}>
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium text-muted-foreground">{label}</CardTitle>
                                <Icon className="size-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-semibold">{value}</div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(280px,0.55fr)]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Recent invoices</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {recentInvoices.length === 0 ? (
                                <p className="text-sm text-muted-foreground">No invoices have been created yet.</p>
                            ) : (
                                <div className="divide-y">
                                    {recentInvoices.map((invoice) => (
                                        <div key={invoice.id} className="flex flex-col gap-3 py-4 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between">
                                            <div className="min-w-0">
                                                <p className="font-medium">Invoice #{invoice.id}</p>
                                                <p className="truncate text-sm text-muted-foreground">
                                                    {[invoice.client, invoice.project, invoice.milestone].filter(Boolean).join(' · ') || 'Billing record'}
                                                </p>
                                            </div>
                                            <div className="flex items-center justify-between gap-4 sm:justify-end">
                                                <span className="font-semibold">{currency.format(invoice.total)}</span>
                                                <Badge variant={invoice.status === 'paid' ? 'secondary' : invoice.status === 'partial' ? 'outline' : 'destructive'}>
                                                    {invoice.status}
                                                </Badge>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <CardTitle>Recent projects</CardTitle>
                            <Button variant="ghost" size="sm" asChild>
                                <Link href="/create-project">Add</Link>
                            </Button>
                        </CardHeader>
                        <CardContent>
                            {recentProjects.length === 0 ? (
                                <p className="text-sm text-muted-foreground">No projects have been created yet.</p>
                            ) : (
                                <div className="divide-y">
                                    {recentProjects.map((project) => (
                                        <div key={project.id} className="py-4 first:pt-0 last:pb-0">
                                            <p className="font-medium">{project.name}</p>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {project.client || 'No client'} · {project.milestones_count} milestones
                                            </p>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
