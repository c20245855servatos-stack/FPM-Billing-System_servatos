import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, CircleCheck, FolderKanban, ReceiptText, Users } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

const steps = [
    { label: 'Clients', description: 'Keep customer details organized.', icon: Users },
    { label: 'Projects', description: 'Plan work around each client.', icon: FolderKanban },
    { label: 'Invoices', description: 'Turn completed work into billing.', icon: ReceiptText },
    { label: 'Payments', description: 'Track collection to completion.', icon: CircleCheck },
];

export default function Welcome() {
    const { auth } = usePage<SharedData>().props;

    return (
        <>
            <Head title="FPM Billing" />
            <main className="min-h-screen overflow-hidden bg-gradient-to-br from-slate-50 via-cyan-50/60 to-white dark:from-background dark:via-cyan-950/20 dark:to-background">
                <header className="mx-auto flex max-w-6xl items-center justify-between px-5 py-6 sm:px-8">
                    <Link href="/" className="flex items-center gap-3 font-semibold tracking-tight">
                        <span className="flex size-10 items-center justify-center rounded-full bg-cyan-600 text-white shadow-lg shadow-cyan-600/20">
                            F
                        </span>
                        FPM Billing
                    </Link>
                    <div className="flex items-center gap-2">
                        {auth.user ? (
                            <Button asChild>
                                <Link href={route('dashboard')}>Open dashboard</Link>
                            </Button>
                        ) : (
                            <>
                                <Button variant="ghost" asChild>
                                    <Link href={route('login')}>Log in</Link>
                                </Button>
                                <Button asChild>
                                    <Link href={route('register')}>Get started</Link>
                                </Button>
                            </>
                        )}
                    </div>
                </header>

                <div className="mx-auto grid max-w-6xl gap-12 px-5 py-16 sm:px-8 lg:grid-cols-[1fr_0.95fr] lg:items-center lg:py-24">
                    <section>
                        <Badge variant="outline" className="mb-6 border-cyan-200 bg-white px-3 py-1 text-cyan-700 dark:bg-background dark:text-cyan-300">
                            Project and billing workflow
                        </Badge>
                        <h1 className="max-w-xl text-5xl font-semibold tracking-tight sm:text-6xl">
                            From project work to <span className="text-cyan-600">paid invoice.</span>
                        </h1>
                        <p className="mt-6 max-w-xl text-lg leading-8 text-muted-foreground">
                            A clear workspace for managing clients, projects, milestones, invoices, and payments in one connected billing flow.
                        </p>
                        <div className="mt-8 flex flex-wrap gap-3">
                            <Button size="lg" asChild>
                                <Link href={auth.user ? route('dashboard') : route('register')}>
                                    {auth.user ? 'View dashboard' : 'Create your account'}
                                    <ArrowRight className="size-4" />
                                </Link>
                            </Button>
                            {!auth.user && (
                                <Button size="lg" variant="outline" asChild>
                                    <Link href={route('login')}>Log in</Link>
                                </Button>
                            )}
                        </div>
                    </section>

                    <Card className="relative border-cyan-100 shadow-xl shadow-cyan-950/5 dark:border-cyan-900">
                        <CardContent className="p-5 sm:p-7">
                            <div className="grid gap-3">
                                {steps.map(({ label, description, icon: Icon }, index) => (
                                    <div key={label}>
                                        <div className="flex items-center gap-4 rounded-xl border bg-card p-4 shadow-sm">
                                            <span className="flex size-11 shrink-0 items-center justify-center rounded-full bg-cyan-50 text-cyan-700 dark:bg-cyan-950 dark:text-cyan-300">
                                                <Icon className="size-5" />
                                            </span>
                                            <div className="min-w-0 flex-1">
                                                <p className="font-semibold">{label}</p>
                                                <p className="mt-1 text-sm text-muted-foreground">{description}</p>
                                            </div>
                                            <Badge variant="secondary" className="rounded-full px-2.5">
                                                {index + 1}
                                            </Badge>
                                        </div>
                                        {index < steps.length - 1 && (
                                            <div className="mx-auto h-3 w-px bg-gradient-to-b from-cyan-300 to-cyan-100" />
                                        )}
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </main>
        </>
    );
}
