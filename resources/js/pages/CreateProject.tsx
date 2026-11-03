import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import * as React from 'react';
import { ArrowLeft, ArrowRight, BriefcaseBusiness, Check, FolderKanban, Sparkles, Users } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';

type Client = {
    id: number;
    company_name: string;
};

type ProjectForm = {
    name: string;
    client_id: string;
};

export default function CreateProject({ clients }: { clients: Client[] }) {
    const { data, setData, post, processing, errors } = useForm<ProjectForm>({
        name: '',
        client_id: '',
    });
    const clientForm = useForm({ company_name: '', email: '' });
    const { flash } = usePage<{ flash: { success?: string } }>().props;
    const [clientDialogOpen, setClientDialogOpen] = React.useState(false);

    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        post('/projects', {
            onSuccess: () => {
                setData('name', '');
                setData('client_id', '');
            },
        });
    };

    const submitClient = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        clientForm.post('/clients', {
            preserveScroll: true,
            onSuccess: () => {
                clientForm.reset();
                setClientDialogOpen(false);
                router.reload({ only: ['clients'] });
            },
        });
    };

    return (
        <>
            <Head title="Create project" />
            <main className="min-h-screen bg-gradient-to-br from-slate-50 via-cyan-50/40 to-white dark:from-background dark:via-cyan-950/10 dark:to-background">
                <header className="border-b bg-background">
                    <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-5 sm:px-8">
                        <Link href="/" className="flex items-center gap-2.5 font-semibold tracking-tight">
                            <span className="flex size-9 items-center justify-center rounded-xl bg-primary text-primary-foreground">
                                <BriefcaseBusiness className="size-4" />
                            </span>
                            <span>FPM Billing</span>
                        </Link>
                        <span className="hidden text-sm text-muted-foreground sm:inline">Project workspace</span>
                    </div>
                </header>

                <div className="mx-auto grid max-w-6xl gap-10 px-5 py-10 sm:px-8 sm:py-14 lg:grid-cols-[minmax(0,1fr)_minmax(420px,0.9fr)] lg:items-center lg:gap-16 lg:py-20">
                    <section className="max-w-xl">
                        <Link
                            href="/dashboard"
                            className="mb-9 inline-flex items-center gap-2 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
                        >
                            <ArrowLeft className="size-4" />
                            Back to dashboard
                        </Link>

                        <div className="mb-5 flex size-12 items-center justify-center rounded-full bg-cyan-100 text-cyan-700 ring-8 ring-cyan-50 dark:bg-cyan-950 dark:text-cyan-300 dark:ring-cyan-950/30">
                            <FolderKanban className="size-6" />
                        </div>
                        <p className="mb-3 text-sm font-semibold uppercase tracking-[0.18em] text-primary">Projects</p>
                        <h1 className="text-4xl font-semibold tracking-tight sm:text-5xl">Give your next project a home.</h1>
                        <p className="mt-5 max-w-lg text-lg leading-8 text-muted-foreground">
                            Create a workspace for the work you do for a client. You can manage project billing from here as your work grows.
                        </p>

                        <div className="mt-10 space-y-5">
                            <div className="flex gap-4">
                                <span className="flex size-9 shrink-0 items-center justify-center rounded-full border bg-background text-sm font-semibold">1</span>
                                <div>
                                    <p className="font-medium">Name the project</p>
                                    <p className="mt-1 text-sm text-muted-foreground">Choose a clear name your team will recognize.</p>
                                </div>
                            </div>
                            <div className="flex gap-4">
                                <span className="flex size-9 shrink-0 items-center justify-center rounded-full border bg-background text-sm font-semibold">2</span>
                                <div>
                                    <p className="font-medium">Connect a client</p>
                                    <p className="mt-1 text-sm text-muted-foreground">Keep project work organized with the right client.</p>
                                </div>
                            </div>
                        </div>

                        <div className="mt-10 inline-flex items-center gap-2 rounded-full border bg-background px-3.5 py-2 text-sm text-muted-foreground">
                            <Sparkles className="size-4 text-primary" />
                            Simple setup, ready for billing
                        </div>
                    </section>

                    <Card className="w-full border-border/70 shadow-xl shadow-black/[0.04]">
                        <CardHeader className="space-y-3 pb-5">
                            <div className="flex size-11 items-center justify-center rounded-xl bg-muted">
                                <FolderKanban className="size-5" />
                            </div>
                            <div>
                                <CardTitle className="text-2xl">Create a project</CardTitle>
                                <CardDescription className="mt-2">Add the project details to get started.</CardDescription>
                            </div>
                        </CardHeader>
                        <CardContent>
                            {flash.success && (
                                <div className="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300">
                                    {flash.success}
                                </div>
                            )}
                            <Dialog open={clientDialogOpen} onOpenChange={setClientDialogOpen}>
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>Add a client</DialogTitle>
                                        <DialogDescription>Create a client, then select it for this project.</DialogDescription>
                                    </DialogHeader>
                                    <form onSubmit={submitClient} className="space-y-4">
                                        <div className="space-y-2">
                                            <Label htmlFor="company_name">Company name</Label>
                                            <Input
                                                id="company_name"
                                                value={clientForm.data.company_name}
                                                onChange={(event) => clientForm.setData('company_name', event.target.value)}
                                                aria-invalid={Boolean(clientForm.errors.company_name)}
                                            />
                                            {clientForm.errors.company_name && (
                                                <p className="text-sm font-medium text-destructive">{clientForm.errors.company_name}</p>
                                            )}
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="client_email">Email</Label>
                                            <Input
                                                id="client_email"
                                                type="email"
                                                value={clientForm.data.email}
                                                onChange={(event) => clientForm.setData('email', event.target.value)}
                                                aria-invalid={Boolean(clientForm.errors.email)}
                                            />
                                            {clientForm.errors.email && (
                                                <p className="text-sm font-medium text-destructive">{clientForm.errors.email}</p>
                                            )}
                                        </div>
                                        <Button type="submit" className="w-full" disabled={clientForm.processing}>
                                            {clientForm.processing ? 'Adding client…' : 'Add client'}
                                        </Button>
                                    </form>
                                </DialogContent>
                            </Dialog>
                            <form onSubmit={submit} className="space-y-6">
                                <div className="space-y-2">
                                    <Label htmlFor="name">Project name</Label>
                                    <Input
                                        id="name"
                                        value={data.name}
                                        onChange={(event) => setData('name', event.target.value)}
                                        placeholder="e.g. Website redesign"
                                        aria-invalid={Boolean(errors.name)}
                                        aria-describedby={errors.name ? 'name-error' : 'name-help'}
                                        autoComplete="off"
                                        className="h-11"
                                    />
                                    {errors.name ? (
                                        <p id="name-error" className="text-sm font-medium text-destructive">
                                            {errors.name}
                                        </p>
                                    ) : (
                                        <p id="name-help" className="text-xs text-muted-foreground">Use at least 2 characters.</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <div className="flex items-center justify-between">
                                        <Label htmlFor="client_id">Client</Label>
                                        <Button type="button" variant="ghost" size="sm" className="h-auto px-2 text-xs" onClick={() => setClientDialogOpen(true)}>
                                            <Users className="size-3.5" />
                                            Add client
                                        </Button>
                                    </div>
                                    <Select value={data.client_id} onValueChange={(value) => setData('client_id', value)}>
                                        <SelectTrigger
                                            id="client_id"
                                            aria-invalid={Boolean(errors.client_id)}
                                            aria-describedby={errors.client_id ? 'client-error' : undefined}
                                            className="h-11"
                                        >
                                            <SelectValue placeholder="Choose a client" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {clients.map((client) => (
                                                <SelectItem key={client.id} value={String(client.id)}>
                                                    {client.company_name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.client_id && (
                                        <p id="client-error" className="text-sm font-medium text-destructive">
                                            {errors.client_id}
                                        </p>
                                    )}
                                    {clients.length === 0 && (
                                        <p className="text-sm text-muted-foreground">No clients are available yet. Add a client before creating a project.</p>
                                    )}
                                </div>

                                <div className="rounded-lg border bg-muted/40 p-4">
                                    <div className="flex gap-3">
                                        <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-background text-primary">
                                            <Check className="size-4" />
                                        </span>
                                        <p className="text-sm leading-6 text-muted-foreground">
                                            Your project will be linked to the selected client, keeping billing details together.
                                        </p>
                                    </div>
                                </div>

                                <Button type="submit" disabled={processing || clients.length === 0} className="h-11 w-full">
                                    {processing ? 'Creating project…' : 'Create project'}
                                    {!processing && <ArrowRight className="size-4" />}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </main>
        </>
    );
}
