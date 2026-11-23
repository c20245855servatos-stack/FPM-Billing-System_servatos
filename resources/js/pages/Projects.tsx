import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import * as React from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ArrowRight, Check, ChevronDown, ChevronLeft, ChevronRight, FolderKanban, FolderX, Pencil, RotateCcw, X } from 'lucide-react';

type ApprovalStatus = 'pending' | 'accepted' | 'rejected';

type Milestone = {
    id: number;
    title: string;
    amount: number;
    status: 'pending' | 'invoiced' | 'paid';
    invoice_id: number | null;
    tax_rate: number | null;
    tax_amount: number | null;
    total_due: number | null;
    invoice_status: 'unpaid' | 'partial' | 'paid' | null;
    amount_paid: number;
};

type Project = {
    id: number;
    name: string;
    price: number;
    default_tax_rate: number;
    milestones_count: number;
    milestones: Milestone[];
    client?: string;
    approval_status: ApprovalStatus;
    is_archived: boolean;
};

type Pagination = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

type Props = {
    projects: Project[];
    pagination?: Pagination;
    show_archived?: boolean;
};

// ─── helpers ────────────────────────────────────────────────────────────────

function splitMilestoneAmount(total: string, count: string): number[] {
    const milestoneCount = Number(count);
    const totalAmount = Number(total);
    if (!Number.isInteger(milestoneCount) || milestoneCount < 1 || milestoneCount > 100 || !Number.isFinite(totalAmount)) return [];
    const totalCents = Math.round(totalAmount * 100);
    if (totalCents < milestoneCount) return [];
    const baseCents = Math.floor(totalCents / milestoneCount);
    const remainder = totalCents % milestoneCount;
    return Array.from({ length: milestoneCount }, (_, i) => (baseCents + (i < remainder ? 1 : 0)) / 100);
}

const currency = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'PHP' });

function paidAmountForMilestone(milestone: Milestone, taxRate: number): number {
    if (milestone.amount_paid > 0) return milestone.amount_paid;
    if (milestone.status === 'paid' && milestone.total_due === null) {
        return milestone.amount + Math.round(milestone.amount * taxRate * 100) / 100;
    }
    return 0;
}

const approvalColor: Record<ApprovalStatus, string> = {
    pending:  'border-amber-300 bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
    accepted: 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
    rejected: 'border-red-300 bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300',
};

const milestoneColor: Record<Milestone['status'], string> = {
    pending:  'text-muted-foreground',
    invoiced: 'border-cyan-300 bg-cyan-50 text-cyan-700 dark:bg-cyan-950 dark:text-cyan-300',
    paid:     'border-emerald-300 bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
};

// ─── small components ────────────────────────────────────────────────────────

function ApprovalBadge({ status }: { status: ApprovalStatus }) {
    return (
        <span className={`inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium ${approvalColor[status]}`}>
            {status.charAt(0).toUpperCase() + status.slice(1)}
        </span>
    );
}

function ReviewActions({ project }: { project: Project }) {
    const [busy, setBusy] = React.useState(false);
    const decide = (status: 'accepted' | 'rejected') => {
        setBusy(true);
        router.patch(`/projects/${project.id}/approval`, { status }, { preserveScroll: true, onFinish: () => setBusy(false) });
    };
    return (
        <div className="flex gap-1.5">
            <Button size="sm" disabled={busy} onClick={() => decide('accepted')} className="h-7 px-2 text-xs">
                <Check className="size-3" /> Accept
            </Button>
            <Button size="sm" variant="destructive" disabled={busy} onClick={() => decide('rejected')} className="h-7 px-2 text-xs">
                <X className="size-3" /> Reject
            </Button>
        </div>
    );
}

// ─── milestone status dropdown ───────────────────────────────────────────────

function MilestoneStatusSelect({ milestone }: { milestone: Milestone }) {
    if (milestone.invoice_id) {
        const status = milestone.invoice_status ?? 'unpaid';
        const label = status === 'paid' ? 'Paid' : status === 'partial' ? 'Partially paid' : 'Unpaid';

        return (
            <Badge variant={status === 'paid' ? 'secondary' : status === 'partial' ? 'outline' : 'destructive'}>
                Invoice #{milestone.invoice_id} · {label}
            </Badge>
        );
    }

    return (
        <Select
            value={milestone.status}
            onValueChange={(value) => router.patch(`/milestones/${milestone.id}/status`, { status: value }, { preserveScroll: true })}
        >
            <SelectTrigger className={`h-6 w-24 text-xs ${milestoneColor[milestone.status]}`}>
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="pending">Pending</SelectItem>
                <SelectItem value="invoiced">Invoiced</SelectItem>
                <SelectItem value="paid">✓ Paid</SelectItem>
            </SelectContent>
        </Select>
    );
}

// ─── milestone formula breakdown (shown inline in table row) ─────────────────

function MilestoneFormula({ milestone }: { milestone: Milestone }) {
    if (milestone.tax_rate === null) return null;
    return (
        <div className="mt-1 space-y-0.5 rounded bg-muted/50 px-2 py-1.5 text-xs text-muted-foreground">
            <div className="flex justify-between">
                <span>Subtotal</span><span>{currency.format(milestone.amount)}</span>
            </div>
            <div className="flex justify-between">
                <span>Tax ({((milestone.tax_rate) * 100).toFixed(2)}%)</span>
                <span>{currency.format(milestone.tax_amount ?? 0)}</span>
            </div>
            <div className="flex justify-between border-t pt-1 font-semibold text-foreground">
                <span>Total due</span><span>{currency.format(milestone.total_due ?? 0)}</span>
            </div>
        </div>
    );
}

// ─── receipt summary ─────────────────────────────────────────────────────────

function ReceiptSummary({ milestones, milestones_count, default_tax_rate }: { milestones: Milestone[]; milestones_count: number; default_tax_rate: number }) {
    const subtotal  = milestones.reduce((s, m) => s + m.amount, 0);
    // For invoiced milestones use actual tax; for pending use the project default rate
    const totalTax  = milestones.reduce((s, m) => {
        if (m.tax_amount !== null) return s + m.tax_amount;
        return s + Math.round(m.amount * default_tax_rate * 100) / 100;
    }, 0);
    const totalDue  = milestones.reduce((s, m) => {
        if (m.total_due !== null) return s + m.total_due;
        const tax = Math.round(m.amount * default_tax_rate * 100) / 100;
        return s + m.amount + tax;
    }, 0);
    const totalPaid = milestones.reduce((s, m) => {
        // Use actual recorded payments first
        if (m.amount_paid > 0) return s + m.amount_paid;
        // Fall back: milestone manually marked paid with no invoice
        if (m.status === 'paid' && m.total_due === null) {
            const tax = Math.round(m.amount * default_tax_rate * 100) / 100;
            return s + m.amount + tax;
        }
        return s;
    }, 0);
    const balance   = totalDue - totalPaid;

    return (
        <div className="mt-2 space-y-1 rounded-lg border bg-muted/30 px-3 py-2.5 text-xs">
            <p className="mb-1.5 font-semibold uppercase tracking-wide text-muted-foreground">Receipt summary</p>
            <div className="flex justify-between text-muted-foreground">
                <span>Subtotal ({milestones_count} milestone{milestones_count === 1 ? '' : 's'})</span>
                <span>{currency.format(subtotal)}</span>
            </div>
            <div className="flex justify-between text-muted-foreground">
                <span>Tax ({(default_tax_rate * 100).toFixed(0)}%)</span><span>{currency.format(totalTax)}</span>
            </div>
            <div className="flex justify-between border-t pt-1 font-semibold text-foreground">
                <span>Total amount due</span><span>{currency.format(totalDue)}</span>
            </div>
            <div className="flex justify-between text-emerald-700 dark:text-emerald-400">
                <span>Paid</span><span>{currency.format(totalPaid)}</span>
            </div>
            <div className="flex justify-between border-t pt-1 font-bold text-foreground">
                <span>Balance</span>
                <span className={balance <= 0 ? 'text-emerald-700 dark:text-emerald-400' : ''}>
                    {currency.format(Math.max(balance, 0))}{balance <= 0 ? ' ✓' : ''}
                </span>
            </div>
        </div>
    );
}

// ─── edit dialog (unchanged logic, shared by both views) ─────────────────────

function EditProjectDialog({ project, endpoint }: { project: Project; endpoint: string }) {
    const [open, setOpen] = React.useState(false);
    const { data, setData, patch, processing, errors } = useForm({
        name: project.name,
        price: String(project.price),
        milestones_count: String(project.milestones_count),
        milestone_titles: project.milestones.map(m => m.title),
    });
    const milestoneAmounts = splitMilestoneAmount(data.price, data.milestones_count);
    const milestoneCount = Number(data.milestones_count);

    const changeMilestoneCount = (value: string) => {
        setData('milestones_count', value);
        const count = Number(value);
        if (!Number.isInteger(count) || count < 0 || count > 100) return;
        const titles = data.milestone_titles.slice(0, count);
        while (titles.length < count) titles.push(`Milestone ${titles.length + 1}`);
        setData('milestone_titles', titles);
    };

    const submit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        patch(endpoint, { preserveScroll: true, onSuccess: () => setOpen(false) });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <Button size="sm" variant="outline" onClick={() => setOpen(true)} className="h-7 px-2 text-xs">
                <Pencil className="size-3" /> Edit
            </Button>
            <DialogContent className="flex max-h-[90vh] flex-col gap-0 overflow-hidden p-0 sm:max-w-lg">
                <DialogHeader className="shrink-0 border-b px-6 py-4">
                    <DialogTitle>Edit project</DialogTitle>
                    <DialogDescription>Update the project details and name each milestone.</DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="flex flex-1 flex-col overflow-hidden">
                    <div className="flex-1 space-y-4 overflow-y-auto px-6 py-4">
                        <div className="space-y-2">
                            <Label htmlFor={`pname-${project.id}`}>Project name</Label>
                            <Input id={`pname-${project.id}`} value={data.name} onChange={e => setData('name', e.target.value)} aria-invalid={Boolean(errors.name)} />
                            {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor={`pprice-${project.id}`}>Project price</Label>
                            <Input
                                id={`pprice-${project.id}`}
                                type="number" min="0" step="0.01"
                                value={data.price}
                                onChange={e => {
                                    const val = e.target.value;
                                    if (val.includes('.') && val.split('.')[1].length > 2) return;
                                    setData('price', val);
                                }}
                                onKeyDown={e => {
                                    const val = data.price;
                                    if (val.includes('.') && val.split('.')[1].length >= 2 && /^\d$/.test(e.key) && !e.ctrlKey && !e.metaKey && e.key !== 'Backspace' && e.key !== 'Delete' && e.key !== 'ArrowLeft' && e.key !== 'ArrowRight' && e.key !== 'Tab') e.preventDefault();
                                }}
                                aria-invalid={Boolean(errors.price)}
                            />
                            {errors.price && <p className="text-sm text-destructive">{errors.price}</p>}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor={`pmcount-${project.id}`}>Number of milestones</Label>
                            <Input id={`pmcount-${project.id}`} type="number" min="0" max="100" step="1" value={data.milestones_count} onChange={e => changeMilestoneCount(e.target.value)} aria-invalid={Boolean(errors.milestones_count)} />
                            {errors.milestones_count && <p className="text-sm text-destructive">{errors.milestones_count}</p>}
                        </div>
                        {data.milestone_titles.map((title, index) => (
                            <div key={index} className="space-y-2">
                                <Label htmlFor={`mt-${project.id}-${index}`}>Milestone {index + 1} name</Label>
                                <Input
                                    id={`mt-${project.id}-${index}`}
                                    value={title}
                                    onChange={e => {
                                        const titles = [...data.milestone_titles];
                                        titles[index] = e.target.value;
                                        setData('milestone_titles', titles);
                                    }}
                                    aria-invalid={Boolean(errors[`milestone_titles.${index}`])}
                                />
                                {errors[`milestone_titles.${index}`] && <p className="text-sm text-destructive">{errors[`milestone_titles.${index}`]}</p>}
                            </div>
                        ))}
                        <div className="space-y-2 rounded-lg border p-3">
                            <p className="text-sm font-medium">Milestone payment split</p>
                            {milestoneCount === 0 ? (
                                <p className="text-sm text-muted-foreground">No milestones will be created.</p>
                            ) : milestoneAmounts.length > 0 ? (
                                <div className="max-h-40 divide-y overflow-y-auto">
                                    {milestoneAmounts.map((amount, i) => (
                                        <div key={i} className="flex justify-between py-2 text-sm">
                                            <span>{data.milestone_titles[i] || `Milestone ${i + 1}`}</span>
                                            <span>{currency.format(amount)}</span>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-sm text-destructive">The project total must be at least ₱0.01 per milestone.</p>
                            )}
                            {milestoneCount > 0 && <p className="text-xs text-muted-foreground">New milestones are scheduled monthly. Existing due dates are kept.</p>}
                        </div>
                    </div>
                    <div className="shrink-0 border-t px-6 py-4">
                        <Button type="submit" className="w-full" disabled={processing}>
                            {processing ? 'Saving…' : 'Save changes'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

// ─── pagination bar ───────────────────────────────────────────────────────────

function PaginationBar({ pagination }: { pagination: Pagination }) {
    const { current_page, last_page, total, from, to } = pagination;
    const go = (page: number) => router.get('', { page }, { preserveScroll: true, preserveState: true });

    return (
        <div className="flex flex-col items-center justify-between gap-3 sm:flex-row">
            <p className="text-sm text-muted-foreground">
                Showing <span className="font-medium">{from ?? 0}–{to ?? 0}</span> of <span className="font-medium">{total}</span> projects
            </p>
            <div className="flex items-center gap-1">
                <Button variant="outline" size="sm" disabled={current_page <= 1} onClick={() => go(current_page - 1)}>
                    <ChevronLeft className="size-4" /> Previous
                </Button>
                {Array.from({ length: last_page }, (_, i) => i + 1).map(page => (
                    <Button
                        key={page}
                        variant={page === current_page ? 'default' : 'outline'}
                        size="sm"
                        className="w-8 px-0"
                        onClick={() => go(page)}
                    >
                        {page}
                    </Button>
                ))}
                <Button variant="outline" size="sm" disabled={current_page >= last_page} onClick={() => go(current_page + 1)}>
                    Next <ChevronRight className="size-4" />
                </Button>
            </div>
        </div>
    );
}

// ─── admin table row ──────────────────────────────────────────────────────────

function AdminProjectRow({ project }: { project: Project }) {
    const [expanded, setExpanded] = React.useState(false);

    return (
        <>
            {/* Main row */}
            <tr className={`border-b transition-colors hover:bg-muted/40 ${project.is_archived ? 'opacity-60' : ''}`}>
                <td className="px-4 py-3">
                    <div className="font-medium">{project.name}</div>
                    <div className="text-xs text-muted-foreground">{project.client ?? '—'}</div>
                </td>
                <td className="px-4 py-3 text-sm">{currency.format(project.price)}</td>
                <td className="px-4 py-3">
                    <ApprovalBadge status={project.approval_status} />
                </td>
                <td className="px-4 py-3 text-center text-sm text-muted-foreground">
                    {project.milestones_count}
                </td>
                <td className="px-4 py-3">
                    {/* Amount due */}
                    {project.milestones.length > 0 && (() => {
                        const totalDue  = project.milestones.reduce((s, m) => s + (m.total_due ?? m.amount), 0);
                        const totalPaid = project.milestones.reduce((sum, milestone) => sum + paidAmountForMilestone(milestone, taxRate), 0);
                        const balance   = totalDue - totalPaid;
                        return (
                            <div className="text-sm font-medium">{currency.format(totalDue)}</div>
                        );
                    })()}
                </td>
                <td className="px-4 py-3">
                    {/* Balance */}
                    {project.milestones.length > 0 && (() => {
                        const totalDue  = project.milestones.reduce((s, m) => s + (m.total_due ?? m.amount), 0);
                        const totalPaid = project.milestones.reduce((sum, milestone) => sum + paidAmountForMilestone(milestone, taxRate), 0);
                        const balance   = Math.max(totalDue - totalPaid, 0);
                        const paid      = balance <= 0;
                        return (
                            <div className={`text-sm font-semibold ${paid ? 'text-emerald-600 dark:text-emerald-400' : 'text-foreground'}`}>
                                {paid ? '✓ Fully paid' : currency.format(balance)}
                            </div>
                        );
                    })()}
                </td>
                <td className="px-4 py-3">
                    <div className="flex items-center justify-end gap-2">
                        {project.approval_status === 'pending' && <ReviewActions project={project} />}
                        {project.approval_status === 'accepted' && !project.is_archived && (
                            <EditProjectDialog project={project} endpoint={`/projects/${project.id}`} />
                        )}
                        {/* Archive button: show when rejected OR fully paid */}
                        {!project.is_archived && (() => {
                            const totalDue  = project.milestones.reduce((s, m) => s + (m.total_due ?? m.amount), 0);
                            const totalPaid = project.milestones.reduce((sum, milestone) => sum + paidAmountForMilestone(milestone, taxRate), 0);
                            const fullyPaid = totalDue > 0 && totalPaid >= totalDue;
                            const canArchive = project.approval_status === 'rejected' || fullyPaid;
                            return canArchive ? (
                                <Button
                                    size="sm"
                                    variant="outline"
                                    className="h-7 border-amber-300 px-2 text-xs text-amber-700 hover:bg-amber-50 dark:text-amber-400"
                                    onClick={() => router.patch(`/projects/${project.id}/archive`, {}, { preserveScroll: true })}
                                >
                                    <FolderX className="size-3" /> Archive
                                </Button>
                            ) : null;
                        })()}
                        {/* Restore button for archived projects */}
                        {project.is_archived && (
                            <Button
                                size="sm"
                                variant="outline"
                                className="h-7 border-cyan-300 px-2 text-xs text-cyan-700 hover:bg-cyan-50 dark:text-cyan-400"
                                onClick={() => router.patch(`/projects/${project.id}/unarchive`, {}, { preserveScroll: true })}
                            >
                                <RotateCcw className="size-3" /> Restore
                            </Button>
                        )}
                        <Button
                            size="sm"
                            variant="ghost"
                            className="h-7 px-2 text-xs text-muted-foreground"
                            onClick={() => setExpanded(v => !v)}
                        >
                            {expanded ? 'Hide' : 'Details'}
                        </Button>
                    </div>
                </td>
            </tr>

            {/* Expanded milestone details */}
            {expanded && (
                <tr className="bg-muted/20">
                    <td colSpan={7} className="px-6 pb-4 pt-2">
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {/* Milestones */}
                            <div className="sm:col-span-2 lg:col-span-2">
                                <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Milestones</p>
                                <div className="divide-y rounded-lg border bg-background">
                                    {project.milestones.map(milestone => (
                                        <div key={milestone.id} className="px-3 py-2 text-sm">
                                            <div className="flex items-center justify-between gap-3">
                                                <span className="truncate text-muted-foreground">{milestone.title}</span>
                                                <div className="flex shrink-0 items-center gap-2">
                                                    <span className="font-medium">{currency.format(milestone.amount)}</span>
                                                    <MilestoneStatusSelect milestone={milestone} />
                                                </div>
                                            </div>
                                            <MilestoneFormula milestone={milestone} />
                                        </div>
                                    ))}
                                </div>
                            </div>
                            {/* Receipt */}
                            <div>
                                <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Summary</p>
                                <ReceiptSummary milestones={project.milestones} milestones_count={project.milestones_count} default_tax_rate={project.default_tax_rate} />
                            </div>
                        </div>
                    </td>
                </tr>
            )}
        </>
    );
}

// ─── admin collapsible card ──────────────────────────────────────────────────

function AdminCard({ project, taxRate, subtotal, totalTax, totalDue, totalPaid, balance, fullyPaid, canArchive }: {
    project: Project;
    taxRate: number; subtotal: number; totalTax: number; totalDue: number;
    totalPaid: number; balance: number; fullyPaid: boolean; canArchive: boolean;
}) {
    const [expanded, setExpanded] = React.useState(false);

    return (
        <div className={`rounded-2xl border bg-background shadow-sm overflow-hidden transition-opacity ${project.is_archived ? 'opacity-60' : ''}`}>

            {/* ── Header row — always visible ── */}
            <div className="flex items-center gap-2 px-4 py-4">

                {/* Left: chevron + info — click to expand */}
                <button
                    type="button"
                    onClick={() => setExpanded(v => !v)}
                    className="flex flex-1 items-center gap-3 text-left min-w-0"
                    aria-expanded={expanded}
                >
                    <ChevronDown
                        className={`size-4 shrink-0 text-muted-foreground transition-transform duration-200 ${expanded ? 'rotate-180' : ''}`}
                    />
                    <div className="flex-1 min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="font-semibold truncate">{project.name}</span>
                            <ApprovalBadge status={project.approval_status} />
                            {project.is_archived && (
                                <span className="inline-flex items-center rounded-full border border-muted px-2 py-0.5 text-xs text-muted-foreground">Archived</span>
                            )}
                        </div>
                        <p className="mt-0.5 text-xs text-muted-foreground">
                            {project.client ?? '—'} · {project.milestones_count} milestone{project.milestones_count === 1 ? '' : 's'}
                        </p>
                    </div>
                </button>

                {/* Right: summary figures + action buttons — always visible */}
                <div className="flex items-center gap-4 shrink-0">
                    {/* Summary numbers */}
                    <div className="hidden sm:flex items-center gap-5 text-sm">
                        <div className="text-right">
                            <p className="text-xs text-muted-foreground">Price</p>
                            <p className="font-medium">{currency.format(project.price)}</p>
                        </div>
                        {totalDue > 0 && (
                            <>
                                <div className="text-right">
                                    <p className="text-xs text-muted-foreground">Total due</p>
                                    <p className="font-semibold">{currency.format(totalDue)}</p>
                                </div>
                                <div className="text-right">
                                    <p className="text-xs text-muted-foreground">Balance</p>
                                    <p className={`font-bold ${fullyPaid ? 'text-emerald-600 dark:text-emerald-400' : ''}`}>
                                        {fullyPaid ? '✓ Paid' : currency.format(Math.max(balance, 0))}
                                    </p>
                                </div>
                            </>
                        )}
                    </div>

                    {/* Action buttons — always visible in header */}
                    <div className="flex items-center gap-1.5">
                        {project.approval_status === 'pending' && <ReviewActions project={project} />}
                        {project.approval_status === 'accepted' && !project.is_archived && (
                            <EditProjectDialog project={project} endpoint={`/projects/${project.id}`} />
                        )}
                        {canArchive && (
                            <Button
                                size="sm" variant="outline"
                                className="h-7 border-amber-300 px-2 text-xs text-amber-700 hover:bg-amber-50 dark:text-amber-400"
                                onClick={() => router.patch(`/projects/${project.id}/archive`, {}, { preserveScroll: true })}
                            >
                                <FolderX className="size-3" /> Archive
                            </Button>
                        )}
                        {project.is_archived && (
                            <Button
                                size="sm" variant="outline"
                                className="h-7 border-cyan-300 px-2 text-xs text-cyan-700 hover:bg-cyan-50 dark:text-cyan-400"
                                onClick={() => router.patch(`/projects/${project.id}/unarchive`, {}, { preserveScroll: true })}
                            >
                                <RotateCcw className="size-3" /> Restore
                            </Button>
                        )}
                    </div>
                </div>
            </div>

            {/* ── Expandable body ── */}
            {expanded && (
                <>
                    {/* Milestones */}
                    {project.milestones.length > 0 ? (
                        <div className="border-t px-6 py-4 space-y-3">
                            <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Milestones</p>
                            <div className="divide-y rounded-xl border overflow-hidden">
                                {project.milestones.map(milestone => {
                                    const estTax   = Math.round(milestone.amount * taxRate * 100) / 100;
                                    const taxRate_ = milestone.tax_rate ?? taxRate;
                                    const taxAmt   = milestone.tax_amount ?? estTax;
                                    const totalAmt = milestone.total_due ?? (milestone.amount + estTax);
                                    return (
                                        <div key={milestone.id} className="px-4 py-3 bg-background">
                                            <div className="flex items-center justify-between gap-3">
                                                <span className="text-sm font-medium truncate">{milestone.title}</span>
                                                <div className="flex shrink-0 items-center gap-2">
                                                    <span className="text-sm font-semibold">{currency.format(milestone.amount)}</span>
                                                    <MilestoneStatusSelect milestone={milestone} />
                                                </div>
                                            </div>
                                            <div className="mt-2 rounded-lg bg-muted/40 px-3 py-2 text-xs space-y-1">
                                                <div className="flex justify-between text-muted-foreground">
                                                    <span>Subtotal</span><span>{currency.format(milestone.amount)}</span>
                                                </div>
                                                <div className="flex justify-between text-muted-foreground">
                                                    <span>Tax ({(taxRate_ * 100).toFixed(2)}%)</span>
                                                    <span>{currency.format(taxAmt)}</span>
                                                </div>
                                                <div className="flex justify-between border-t pt-1 font-semibold text-foreground">
                                                    <span>Total due</span><span>{currency.format(totalAmt)}</span>
                                                </div>
                                            </div>
                                            <div className="mt-2 flex justify-end">
                                                {milestone.invoice_id ? (
                                                    <Link
                                                        href={`/invoices?invoice_id=${milestone.invoice_id}`}
                                                        className="text-sm font-medium text-primary underline-offset-4 hover:underline"
                                                    >
                                                        View invoice #{milestone.invoice_id}
                                                    </Link>
                                                ) : project.approval_status === 'accepted' && !project.is_archived ? (
                                                    <Link
                                                        href={`/invoices?milestone_id=${milestone.id}`}
                                                        className="text-sm font-medium text-primary underline-offset-4 hover:underline"
                                                    >
                                                        Create invoice
                                                    </Link>
                                                ) : (
                                                    <span className="text-xs text-muted-foreground">No invoice created</span>
                                                )}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    ) : (
                        <div className="border-t px-6 py-4">
                            <p className="text-sm text-muted-foreground italic">No milestones yet.</p>
                        </div>
                    )}

                    {/* Receipt summary */}
                    {project.milestones.length > 0 && (
                        <div className="mx-6 mb-5 rounded-xl border bg-muted/30 overflow-hidden">
                            <div className="px-4 py-3 border-b bg-muted/40">
                                <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Receipt summary</p>
                            </div>
                            <div className="px-4 py-3 space-y-1.5 text-sm">
                                <div className="flex justify-between text-muted-foreground">
                                    <span>Subtotal ({project.milestones_count} milestone{project.milestones_count === 1 ? '' : 's'})</span>
                                    <span>{currency.format(subtotal)}</span>
                                </div>
                                <div className="flex justify-between text-muted-foreground">
                                    <span>Tax ({(taxRate * 100).toFixed(0)}%)</span>
                                    <span>{currency.format(totalTax)}</span>
                                </div>
                                <div className="flex justify-between font-semibold border-t pt-2 mt-1">
                                    <span>Total amount due</span><span>{currency.format(totalDue)}</span>
                                </div>
                                <div className="flex justify-between text-emerald-700 dark:text-emerald-400">
                                    <span>Paid</span><span>{currency.format(totalPaid)}</span>
                                </div>
                                <div className={`flex justify-between font-bold border-t pt-2 mt-1 ${fullyPaid ? 'text-emerald-700 dark:text-emerald-400' : ''}`}>
                                    <span>Balance</span>
                                    <span>{fullyPaid ? '✓ Fully paid' : currency.format(Math.max(balance, 0))}</span>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Admin actions — now in header, removed from here */}
                </>
            )}
        </div>
    );
}

// ─── page ─────────────────────────────────────────────────────────────────────

export default function Projects({ projects, pagination, show_archived = false }: Props) {
    const { auth } = usePage<SharedData>().props;
    const isAdmin = auth.role === 'admin';

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Projects', href: isAdmin ? '/projects' : '/my-projects' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Projects" />
            <div className="flex flex-1 flex-col gap-6 bg-slate-50/60 p-4 md:p-6 dark:bg-background">

                {/* ── Header ── */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Projects</h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {isAdmin
                                ? `All client projects${pagination ? ` · ${pagination.total} total` : ''}`
                                : 'Projects created for your client account.'}
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        {isAdmin && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => router.get('/projects', { show_archived: show_archived ? undefined : '1' }, { preserveState: true })}
                            >
                                {show_archived ? (
                                    <><FolderKanban className="size-4" /> Active projects</>
                                ) : (
                                    <><FolderX className="size-4" /> Archived projects</>
                                )}
                            </Button>
                        )}
                        {!isAdmin && (
                            <Button asChild>
                                <Link href="/create-project">
                                    Create project <ArrowRight className="size-4" />
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                {/* ── Empty state ── */}
                {projects.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center gap-3 py-12 text-center">
                            <FolderKanban className="size-8 text-muted-foreground" />
                            <p className="font-medium">No projects yet</p>
                            <p className="text-sm text-muted-foreground">
                                {isAdmin ? 'No projects have been created yet.' : 'Create a project to get started.'}
                            </p>
                        </CardContent>
                    </Card>

                ) : isAdmin ? (
                    /* ── Admin: collapsible card view ── */
                    <div className="flex flex-col gap-3">
                        {projects.map((project) => {
                            const taxRate   = project.default_tax_rate;
                            const subtotal  = project.milestones.reduce((s, m) => s + m.amount, 0);
                            const totalTax  = project.milestones.reduce((s, m) => {
                                if (m.tax_amount !== null) return s + m.tax_amount;
                                return s + Math.round(m.amount * taxRate * 100) / 100;
                            }, 0);
                            const totalDue  = project.milestones.reduce((s, m) => {
                                if (m.total_due !== null) return s + m.total_due;
                                const tax = Math.round(m.amount * taxRate * 100) / 100;
                                return s + m.amount + tax;
                            }, 0);
                            const totalPaid = project.milestones.reduce((sum, milestone) => sum + paidAmountForMilestone(milestone, taxRate), 0);
                            const balance   = totalDue - totalPaid;
                            const fullyPaid = totalDue > 0 && balance <= 0;
                            const canArchive = !project.is_archived && (project.approval_status === 'rejected' || fullyPaid);

                            return <AdminCard
                                key={project.id}
                                project={project}
                                taxRate={taxRate}
                                subtotal={subtotal}
                                totalTax={totalTax}
                                totalDue={totalDue}
                                totalPaid={totalPaid}
                                balance={balance}
                                fullyPaid={fullyPaid}
                                canArchive={canArchive}
                            />;
                        })}

                        {pagination && pagination.last_page > 1 && (
                            <PaginationBar pagination={pagination} />
                        )}
                    </div>

                ) : (
                    /* ── Client: card view ── */
                    <div className="flex flex-col gap-5 max-w-2xl">
                        {projects.map((project) => {
                            const taxRate   = project.default_tax_rate;
                            const subtotal  = project.milestones.reduce((s, m) => s + m.amount, 0);
                            const totalTax  = project.milestones.reduce((s, m) => {
                                if (m.tax_amount !== null) return s + m.tax_amount;
                                return s + Math.round(m.amount * taxRate * 100) / 100;
                            }, 0);
                            const totalDue  = project.milestones.reduce((s, m) => {
                                if (m.total_due !== null) return s + m.total_due;
                                const tax = Math.round(m.amount * taxRate * 100) / 100;
                                return s + m.amount + tax;
                            }, 0);
                            const totalPaid = project.milestones.reduce((sum, milestone) => sum + paidAmountForMilestone(milestone, taxRate), 0);
                            const balance   = totalDue - totalPaid;
                            const fullyPaid = totalDue > 0 && balance <= 0;

                            return (
                                <div key={project.id} className="rounded-2xl border bg-background shadow-sm overflow-hidden">

                                    {/* ── Card header ── */}
                                    <div className="flex items-start justify-between gap-4 px-6 py-5 border-b bg-muted/20">
                                        <div>
                                            <h2 className="text-lg font-semibold tracking-tight">{project.name}</h2>
                                            <div className="mt-1.5 flex items-center gap-2">
                                                <ApprovalBadge status={project.approval_status} />
                                                <span className="text-xs text-muted-foreground">
                                                    {project.milestones_count} milestone{project.milestones_count === 1 ? '' : 's'}
                                                </span>
                                            </div>
                                        </div>
                                        <div className="text-right shrink-0">
                                            <p className="text-xl font-bold">{currency.format(project.price)}</p>
                                            {fullyPaid && (
                                                <p className="text-xs font-medium text-emerald-600 dark:text-emerald-400 mt-0.5">✓ Fully paid</p>
                                            )}
                                        </div>
                                    </div>

                                    {/* ── Milestones ── */}
                                    {project.milestones.length > 0 ? (
                                        <div className="px-6 py-4 space-y-3">
                                            <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Milestones</p>
                                            <div className="divide-y rounded-xl border overflow-hidden">
                                                {project.milestones.map(milestone => (
                                                    <div key={milestone.id} className="px-4 py-3 bg-background">
                                                        <div className="flex items-center justify-between gap-3">
                                                            <span className="text-sm font-medium truncate">{milestone.title}</span>
                                                            <div className="flex shrink-0 items-center gap-2">
                                                                <span className="text-sm font-semibold">{currency.format(milestone.amount)}</span>
                                                                <span className={`inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium ${milestoneColor[milestone.status]}`}>
                                                                    {milestone.status === 'paid' ? '✓ Paid' : milestone.status === 'invoiced' ? 'Invoiced' : 'Pending'}
                                                                </span>
                                                            </div>
                                                        </div>
                                                        {milestone.tax_rate !== null && (
                                                            <div className="mt-2 rounded-lg bg-muted/40 px-3 py-2 text-xs space-y-1">
                                                                <div className="flex justify-between text-muted-foreground">
                                                                    <span>Subtotal</span>
                                                                    <span>{currency.format(milestone.amount)}</span>
                                                                </div>
                                                                <div className="flex justify-between text-muted-foreground">
                                                                    <span>Tax ({((milestone.tax_rate) * 100).toFixed(2)}%)</span>
                                                                    <span>{currency.format(milestone.tax_amount ?? 0)}</span>
                                                                </div>
                                                                <div className="flex justify-between border-t pt-1 font-semibold text-foreground">
                                                                    <span>Total due</span>
                                                                    <span>{currency.format(milestone.total_due ?? 0)}</span>
                                                                </div>
                                                            </div>
                                                        )}
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="px-6 py-4">
                                            <p className="text-sm text-muted-foreground italic">No milestones yet.</p>
                                        </div>
                                    )}

                                    {/* ── Receipt summary ── */}
                                    {project.milestones.length > 0 && (
                                        <div className="mx-6 mb-5 rounded-xl border bg-muted/30 overflow-hidden">
                                            <div className="px-4 py-3 border-b bg-muted/40">
                                                <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Receipt summary</p>
                                            </div>
                                            <div className="px-4 py-3 space-y-1.5 text-sm">
                                                <div className="flex justify-between text-muted-foreground">
                                                    <span>Subtotal ({project.milestones_count} milestone{project.milestones_count === 1 ? '' : 's'})</span>
                                                    <span>{currency.format(subtotal)}</span>
                                                </div>
                                                <div className="flex justify-between text-muted-foreground">
                                                    <span>Tax ({(taxRate * 100).toFixed(0)}%)</span>
                                                    <span>{currency.format(totalTax)}</span>
                                                </div>
                                                <div className="flex justify-between font-semibold border-t pt-2 mt-1">
                                                    <span>Total amount due</span>
                                                    <span>{currency.format(totalDue)}</span>
                                                </div>
                                                <div className="flex justify-between text-emerald-700 dark:text-emerald-400">
                                                    <span>Paid</span>
                                                    <span>{currency.format(totalPaid)}</span>
                                                </div>
                                                <div className={`flex justify-between font-bold border-t pt-2 mt-1 ${fullyPaid ? 'text-emerald-700 dark:text-emerald-400' : ''}`}>
                                                    <span>Balance</span>
                                                    <span>{fullyPaid ? '✓ Fully paid' : currency.format(Math.max(balance, 0))}</span>
                                                </div>
                                            </div>
                                        </div>
                                    )}

                                    {/* ── Footer actions ── */}
                                    {project.approval_status === 'accepted' && (
                                        <div className="px-6 pb-5">
                                            <EditProjectDialog project={project} endpoint={`/my-projects/${project.id}`} />
                                        </div>
                                    )}
                                    {project.approval_status === 'pending' && (
                                        <div className="px-6 pb-5">
                                            <p className="text-sm text-muted-foreground">Your proposal is waiting for admin review.</p>
                                        </div>
                                    )}
                                    {project.approval_status === 'rejected' && (
                                        <div className="px-6 pb-5">
                                            <p className="text-sm text-destructive font-medium">This project was rejected.</p>
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

