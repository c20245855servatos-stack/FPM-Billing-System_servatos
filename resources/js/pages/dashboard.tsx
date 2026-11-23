import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    ArrowRight, BadgeCheck, CircleDollarSign, ClipboardList,
    FolderKanban, ReceiptText, TrendingUp, Users, Wallet,
} from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

const currency = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'PHP' });

// ─── shared types ─────────────────────────────────────────────────────────────

type RecentInvoice = {
    id: number;
    status: 'unpaid' | 'partial' | 'paid';
    total: number;
    client?: string;
    project?: string;
    milestone?: string;
};

type RecentProject = {
    id: number;
    name: string;
    client?: string;
    milestones_count: number;
    approval_status?: string;
};

// ─── Admin dashboard ──────────────────────────────────────────────────────────

type AdminProps = {
    role: 'admin';
    summary: { clients: number; projects: number; unpaid: number; paid_amount: number };
    recentInvoices: RecentInvoice[];
    recentProjects: RecentProject[];
};

function AdminDashboard({ summary, recentInvoices, recentProjects }: Omit<AdminProps, 'role'>) {
    const cards = [
        { label: 'Total clients',       value: summary.clients,                   icon: Users,            href: '/clients' },
        { label: 'Active projects',     value: summary.projects,                  icon: FolderKanban,     href: '/projects' },
        { label: 'Invoices to collect', value: summary.unpaid,                    icon: ReceiptText,      href: '/invoices' },
        { label: 'Paid revenue',        value: currency.format(summary.paid_amount), icon: CircleDollarSign, href: undefined },
    ];

    return (
        <div className="flex flex-1 flex-col gap-6 bg-slate-50/60 p-4 md:p-6 dark:bg-background">

            {/* Header */}
            <div>
                <h1 className="text-2xl font-semibold tracking-tight">Billing overview</h1>
                <p className="mt-1 text-sm text-muted-foreground">Your freelance business at a glance.</p>
            </div>

            {/* Summary cards */}
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {cards.map(({ label, value, icon: Icon, href }) => {
                    const card = (
                        <Card className={`h-full ${href ? 'transition-colors hover:bg-muted/40 cursor-pointer' : ''}`}>
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium text-muted-foreground">{label}</CardTitle>
                                <Icon className="size-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-semibold">{value}</div>
                            </CardContent>
                        </Card>
                    );
                    return href ? (
                        <Link key={label} href={href} className="block rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                            {card}
                        </Link>
                    ) : <div key={label}>{card}</div>;
                })}
            </div>

            {/* Workflow banner */}
            <Card className="overflow-hidden border-cyan-200 bg-gradient-to-r from-white via-cyan-50/70 to-white py-0 dark:border-cyan-900 dark:from-background dark:via-cyan-950/30 dark:to-background">
                <CardContent className="flex flex-col gap-5 p-5 md:flex-row md:items-center md:justify-between">
                    <div className="flex items-start gap-3">
                        <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-cyan-100 text-cyan-700 dark:bg-cyan-950 dark:text-cyan-300">
                            <TrendingUp className="size-5" />
                        </div>
                        <div>
                            <p className="font-semibold">Project-to-payment flow</p>
                            <p className="mt-1 text-sm text-muted-foreground">Keep every project moving from setup to collection.</p>
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-2 text-xs font-medium">
                        {['Client', 'Project', 'Milestone', 'Invoice', 'Payment'].map((step, i) => (
                            <div key={step} className="flex items-center gap-2">
                                <Badge variant="outline" className={i === 4 ? 'border-emerald-300 bg-white text-emerald-700 dark:bg-background' : 'bg-white dark:bg-background'}>
                                    {i + 1}. {step}
                                </Badge>
                                {i < 4 && <ArrowRight className="size-4 text-cyan-500" />}
                            </div>
                        ))}
                    </div>
                </CardContent>
            </Card>

            {/* Recent data */}
            <div className="grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(280px,0.55fr)]">
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle>Recent invoices</CardTitle>
                        <Button variant="ghost" size="sm" asChild><Link href="/invoices">View all</Link></Button>
                    </CardHeader>
                    <CardContent>
                        {recentInvoices.length === 0 ? (
                            <p className="text-sm text-muted-foreground">No invoices yet.</p>
                        ) : (
                            <div className="divide-y">
                                {recentInvoices.map(invoice => (
                                    <div key={invoice.id} className="flex flex-col gap-3 py-4 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between">
                                        <div className="min-w-0">
                                            <p className="font-medium">Invoice #{invoice.id}</p>
                                            <p className="truncate text-sm text-muted-foreground">
                                                {[invoice.client, invoice.project, invoice.milestone].filter(Boolean).join(' · ') || 'Billing record'}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-3">
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
                        <Button variant="ghost" size="sm" asChild><Link href="/projects">View all</Link></Button>
                    </CardHeader>
                    <CardContent>
                        {recentProjects.length === 0 ? (
                            <p className="text-sm text-muted-foreground">No projects yet.</p>
                        ) : (
                            <div className="divide-y">
                                {recentProjects.map(project => (
                                    <div key={project.id} className="py-4 first:pt-0 last:pb-0">
                                        <p className="font-medium">{project.name}</p>
                                        <p className="mt-0.5 text-sm text-muted-foreground">
                                            {project.client} · {project.milestones_count} milestone{project.milestones_count === 1 ? '' : 's'}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}

// ─── Client dashboard ─────────────────────────────────────────────────────────

type ClientProps = {
    role: 'user';
    summary: { projects: number; proposals: number; total_due: number; total_paid: number; balance: number };
    recentInvoices: RecentInvoice[];
    recentProjects: RecentProject[];
};

function ClientDashboard({ summary, recentInvoices, recentProjects }: Omit<ClientProps, 'role'>) {
    const fullyPaid = summary.total_due > 0 && summary.balance <= 0;

    const cards = [
        { label: 'My projects',      value: summary.projects,                     icon: FolderKanban,  href: '/my-projects' },
        { label: 'Pending proposals',value: summary.proposals,                    icon: ClipboardList, href: '/invoices' },
        { label: 'Total amount due', value: currency.format(summary.total_due),   icon: Wallet,        href: undefined },
        { label: 'Total paid',       value: currency.format(summary.total_paid),  icon: BadgeCheck,    href: undefined },
    ];

    return (
        <div className="flex flex-1 flex-col gap-6 bg-slate-50/60 p-4 md:p-6 dark:bg-background">

            {/* Header */}
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">My billing overview</h1>
                    <p className="mt-1 text-sm text-muted-foreground">Track your projects, invoices, and payments.</p>
                </div>
                <Button asChild>
                    <Link href="/create-project">
                        New project proposal <ArrowRight className="size-4" />
                    </Link>
                </Button>
            </div>

            {/* Summary cards */}
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {cards.map(({ label, value, icon: Icon, href }) => {
                    const card = (
                        <Card className={`h-full ${href ? 'transition-colors hover:bg-muted/40 cursor-pointer' : ''}`}>
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium text-muted-foreground">{label}</CardTitle>
                                <Icon className="size-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-semibold">{value}</div>
                            </CardContent>
                        </Card>
                    );
                    return href ? (
                        <Link key={label} href={href} className="block rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                            {card}
                        </Link>
                    ) : <div key={label}>{card}</div>;
                })}
            </div>

            {/* Balance banner */}
            <Card className={`overflow-hidden py-0 ${fullyPaid ? 'border-emerald-200 bg-gradient-to-r from-white via-emerald-50/60 to-white dark:border-emerald-900 dark:via-emerald-950/20' : 'border-cyan-200 bg-gradient-to-r from-white via-cyan-50/70 to-white dark:border-cyan-900 dark:via-cyan-950/30'}`}>
                <CardContent className="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <div className={`flex size-10 shrink-0 items-center justify-center rounded-full ${fullyPaid ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-cyan-100 text-cyan-700 dark:bg-cyan-950 dark:text-cyan-300'}`}>
                            {fullyPaid ? <BadgeCheck className="size-5" /> : <Wallet className="size-5" />}
                        </div>
                        <div>
                            <p className="font-semibold">
                                {fullyPaid ? 'All invoices paid — great work!' : 'Outstanding balance'}
                            </p>
                            <p className="mt-0.5 text-sm text-muted-foreground">
                                {fullyPaid
                                    ? 'You have no remaining balance.'
                                    : `${currency.format(summary.total_paid)} paid of ${currency.format(summary.total_due)} total.`}
                            </p>
                        </div>
                    </div>
                    <div className="text-right">
                        <p className="text-xs text-muted-foreground">Balance</p>
                        <p className={`text-2xl font-bold ${fullyPaid ? 'text-emerald-600 dark:text-emerald-400' : ''}`}>
                            {fullyPaid ? '✓ Fully paid' : currency.format(summary.balance)}
                        </p>
                    </div>
                </CardContent>
            </Card>

            {/* Recent data */}
            <div className="grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(280px,0.55fr)]">
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle>My invoices</CardTitle>
                        <Button variant="ghost" size="sm" asChild><Link href="/invoices">View all</Link></Button>
                    </CardHeader>
                    <CardContent>
                        {recentInvoices.length === 0 ? (
                            <p className="text-sm text-muted-foreground">No invoices yet.</p>
                        ) : (
                            <div className="divide-y">
                                {recentInvoices.map(invoice => (
                                    <div key={invoice.id} className="flex flex-col gap-3 py-4 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between">
                                        <div className="min-w-0">
                                            <p className="font-medium">Invoice #{invoice.id}</p>
                                            <p className="truncate text-sm text-muted-foreground">
                                                {[invoice.project, invoice.milestone].filter(Boolean).join(' · ') || 'Billing record'}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-3">
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
                        <CardTitle>My projects</CardTitle>
                        <Button variant="ghost" size="sm" asChild><Link href="/my-projects">View all</Link></Button>
                    </CardHeader>
                    <CardContent>
                        {recentProjects.length === 0 ? (
                            <p className="text-sm text-muted-foreground">No active projects yet.</p>
                        ) : (
                            <div className="divide-y">
                                {recentProjects.map(project => (
                                    <div key={project.id} className="py-4 first:pt-0 last:pb-0">
                                        <p className="font-medium">{project.name}</p>
                                        <p className="mt-0.5 text-sm text-muted-foreground">
                                            {project.milestones_count} milestone{project.milestones_count === 1 ? '' : 's'}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}

// ─── page ─────────────────────────────────────────────────────────────────────

type Props = AdminProps | ClientProps;

export default function Dashboard(props: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            {props.role === 'admin'
                ? <AdminDashboard {...props} />
                : <ClientDashboard {...props} />
            }
        </AppLayout>
    );
}
