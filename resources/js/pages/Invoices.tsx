import AppLayout from '@/layouts/app-layout';
import { type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, CreditCard, Pencil, Plus, Search } from 'lucide-react';
import * as React from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

// ─── Types ────────────────────────────────────────────────────────────────────

type Payment = { id: number; amount_paid: string; paid_at: string; method: string };

type Invoice = {
    id: number;
    status: 'unpaid' | 'partial' | 'paid';
    total_amount_due: string;
    subtotal: number;
    tax_rate: number;
    tax_amount: string;
    amount_paid: number;
    balance_due: number;
    milestone: { title: string; amount: number; project: { name: string; client: { company_name: string } } };
    payments: Payment[];
};

type AvailableMilestone = {
    id: number;
    title: string;
    amount: number;
    project_name: string;
    client_name: string | null;
    default_tax_rate: number;
};

type InvoicePagination = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

type InvoiceStatusFilter = 'all' | 'unpaid' | 'partial' | 'paid';

type Proposal = {
    id: number;
    name: string;
    description: string | null;
    price: number;
    default_tax_rate: number;
    admin_counter_price: number | null;
    client_counter_price: number | null;
    last_counter_by: 'admin' | 'client' | null;
    client?: string;
    approval_status: 'pending' | 'countered' | 'rejected';
};

type Props = {
    invoices: { data: Invoice[] };
    proposals: Proposal[];
    available_milestones: AvailableMilestone[];
    selected_milestone_id: number | null;
    invoice_pagination: InvoicePagination;
    invoice_counts: Record<InvoiceStatusFilter, number>;
    filters: { status: InvoiceStatusFilter; search: string; invoice_id: number | null };
};

const currency = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'PHP' });

function limitToTwoDecimals(value: string): string {
    const cleaned = value.replace(/[^\d.]/g, '');
    const [whole = '', decimal] = cleaned.split('.');

    if (cleaned.includes('.')) {
        return `${whole}.${(decimal ?? '').slice(0, 2)}`;
    }

    return whole;
}

function formatTwoDecimals(value: string): string {
    if (!value) return '';
    const number = Number(value);
    return Number.isFinite(number) ? number.toFixed(2) : '';
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function StatusBadge({ status }: { status: Proposal['approval_status'] }) {
    const map: Record<string, string> = {
        pending:   'border-amber-300 bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
        countered: 'border-blue-300 bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
        rejected:  'border-red-300 bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300',
    };
    const labels: Record<string, string> = {
        pending: 'Pending review', countered: 'Negotiating', rejected: 'Rejected',
    };
    return <span className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium ${map[status]}`}>{labels[status]}</span>;
}

/** Reusable counter-price input dialog */
function CounterDialog({
    open, onClose, onSubmit, processing, title, description, fieldLabel, fieldKey,
}: {
    open: boolean; onClose: () => void;
    onSubmit: (price: string) => void;
    processing: boolean;
    title: string; description: React.ReactNode; fieldLabel: string; fieldKey: string;
}) {
    const [price, setPrice] = React.useState('');
    return (
        <Dialog open={open} onOpenChange={o => { if (!o) { setPrice(''); onClose(); } }}>
            <DialogContent className="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription asChild><div>{description}</div></DialogDescription>
                </DialogHeader>
                <div className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor={fieldKey}>{fieldLabel}</Label>
                        <Input
                            id={fieldKey}
                            type="number" min="0.01" step="0.01" placeholder="0.00"
                            value={price}
                            onChange={e => {
                                const v = e.target.value;
                                if (v.includes('.') && v.split('.')[1].length > 2) return;
                                setPrice(v);
                            }}
                        />
                    </div>
                    <div className="flex gap-2">
                        <Button type="button" variant="outline" className="flex-1" onClick={() => { setPrice(''); onClose(); }}>Cancel</Button>
                        <Button className="flex-1" disabled={processing || !price} onClick={() => onSubmit(price)}>
                            {processing ? 'Sending...' : 'Send counter'}
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}

// ─── Negotiation history strip ────────────────────────────────────────────────

function NegotiationHistory({ proposal }: { proposal: Proposal }) {
    const steps: { label: string; price: number; by: string; active?: boolean }[] = [
        { label: 'Client proposed', price: proposal.price, by: 'client' },
    ];
    if (proposal.admin_counter_price) {
        steps.push({ label: 'Admin countered', price: proposal.admin_counter_price, by: 'admin' });
    }
    if (proposal.client_counter_price) {
        steps.push({ label: 'Client countered', price: proposal.client_counter_price, by: 'client' });
    }
    if (steps.length <= 1) return null;
    return (
        <div className="mt-3 space-y-1 rounded-lg bg-muted/40 px-3 py-2 text-xs">
            <p className="font-semibold text-muted-foreground uppercase tracking-wide mb-1">Negotiation history</p>
            {steps.map((s, i) => (
                <div key={i} className="flex justify-between text-muted-foreground">
                    <span>{s.label}</span>
                    <span className="font-medium text-foreground">{currency.format(s.price)}</span>
                </div>
            ))}
        </div>
    );
}

// ─── Admin proposal card ──────────────────────────────────────────────────────

/** Tax preview shown inside the Accept confirmation dialog */
function TaxPreview({ price, taxRate = 0.12 }: { price: number; taxRate?: number }) {
    const tax   = Math.round(price * taxRate * 100) / 100;
    const total = Math.round((price + tax) * 100) / 100;
    return (
        <div className="mt-3 rounded-lg border bg-muted/40 px-4 py-3 text-sm space-y-1.5">
            <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground mb-1">Price breakdown</p>
            <div className="flex justify-between text-muted-foreground">
                <span>Agreed price</span><span>{currency.format(price)}</span>
            </div>
            <div className="flex justify-between text-muted-foreground">
                <span>Tax ({(taxRate * 100).toFixed(0)}%)</span>
                <span>{currency.format(tax)}</span>
            </div>
            <div className="flex justify-between font-semibold border-t pt-1.5 text-foreground">
                <span>Total amount due</span><span>{currency.format(total)}</span>
            </div>
        </div>
    );
}

/** Confirmation dialog shown before admin accepts — displays agreed price + tax */
function AcceptDialog({
    open, onClose, onConfirm, processing, price,
}: {
    open: boolean; onClose: () => void; onConfirm: () => void;
    processing: boolean; price: number;
}) {
    return (
        <Dialog open={open} onOpenChange={o => { if (!o) onClose(); }}>
            <DialogContent className="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>Accept proposal</DialogTitle>
                    <DialogDescription>
                        Review the agreed price and tax before accepting. The project will become active immediately.
                    </DialogDescription>
                </DialogHeader>
                <TaxPreview price={price} />
                <div className="flex gap-2 mt-2">
                    <Button type="button" variant="outline" className="flex-1" onClick={onClose}>Cancel</Button>
                    <Button className="flex-1" disabled={processing} onClick={onConfirm}>
                        {processing ? 'Accepting...' : 'Confirm & accept'}
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}

function AdminProposalCard({ proposal }: { proposal: Proposal }) {
    const [counterOpen, setCounterOpen] = React.useState(false);
    const [acceptOpen, setAcceptOpen]   = React.useState(false);
    const [busy, setBusy] = React.useState(false);

    const decide = (status: 'accepted' | 'rejected') => {
        setBusy(true);
        router.patch(`/projects/${proposal.id}/approval`, { status }, { preserveScroll: true, onFinish: () => setBusy(false) });
    };

    const sendCounter = (price: string) => {
        setBusy(true);
        router.patch(`/projects/${proposal.id}/counter`, { admin_counter_price: price }, {
            preserveScroll: true,
            onFinish: () => { setBusy(false); setCounterOpen(false); },
        });
    };

    const agreeClientCounter = () => {
        setBusy(true);
        router.patch(`/projects/${proposal.id}/agree-client-counter`, {}, { preserveScroll: true, onFinish: () => setBusy(false) });
    };

    // Client countered back — admin must respond to client's price
    const clientJustCountered = proposal.last_counter_by === 'client' && proposal.client_counter_price !== null;
    // Admin already sent a counter — waiting for client's response
    const waitingForClient = proposal.last_counter_by === 'admin' || (proposal.last_counter_by === null && proposal.admin_counter_price !== null);

    const latestPrice = proposal.client_counter_price ?? proposal.admin_counter_price ?? proposal.price;
    // The price to preview in the accept dialog — use client's counter if that's what's being accepted
    const acceptPrice = clientJustCountered ? proposal.client_counter_price! : proposal.price;

    return (
        <div className={`rounded-xl border p-4 ${proposal.approval_status === 'rejected' ? 'opacity-60' : ''}`}>
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <p className="font-semibold">{proposal.name}</p>
                        <StatusBadge status={proposal.approval_status} />
                        {clientJustCountered && (
                            <span className="inline-flex items-center rounded-full border border-orange-300 bg-orange-50 px-2 py-0.5 text-xs font-medium text-orange-700">
                                Client countered — your turn
                            </span>
                        )}
                    </div>
                    <p className="mt-0.5 text-sm text-muted-foreground">{proposal.client}</p>
                    {proposal.description && <p className="mt-2 text-sm text-muted-foreground leading-relaxed">{proposal.description}</p>}
                </div>
                <div className="shrink-0 text-right space-y-1">
                    <p className="text-xs text-muted-foreground">Current offer</p>
                    <p className="text-xl font-bold">{currency.format(latestPrice)}</p>
                    {proposal.price !== latestPrice && (
                        <p className="text-xs text-muted-foreground line-through">{currency.format(proposal.price)} (original)</p>
                    )}
                </div>
            </div>

            <NegotiationHistory proposal={proposal} />

            {/* ── Tax preview — always visible ── */}
            <TaxPreview price={latestPrice} taxRate={proposal.default_tax_rate} />

            {proposal.approval_status !== 'rejected' && (
                <div className="mt-4 flex flex-wrap gap-2 border-t pt-3">
                    {clientJustCountered ? (
                        /* Client countered back — admin can accept client's price or counter again */
                        <>
                            <Button size="sm" disabled={busy} onClick={() => setAcceptOpen(true)}>
                                Accept {currency.format(proposal.client_counter_price!)}
                            </Button>
                            <Button size="sm" variant="outline" disabled={busy} onClick={() => setCounterOpen(true)}>
                                Counter again
                            </Button>
                        </>
                    ) : waitingForClient ? (
                        /* Admin already countered — must wait for client response */
                        <p className="text-xs text-muted-foreground italic py-1">
                            Waiting for client to respond to your counter offer of {currency.format(proposal.admin_counter_price!)}.
                        </p>
                    ) : (
                        /* Fresh proposal — admin can accept directly or counter */
                        <>
                            <Button size="sm" disabled={busy} onClick={() => setAcceptOpen(true)}>
                                Accept proposal
                            </Button>
                            <Button size="sm" variant="outline" disabled={busy} onClick={() => setCounterOpen(true)}>
                                Counter price
                            </Button>
                        </>
                    )}
                    {/* Reject always visible unless waiting for client */}
                    {!waitingForClient && (
                        <Button size="sm" variant="destructive" disabled={busy} onClick={() => decide('rejected')}>
                            Reject
                        </Button>
                    )}
                </div>
            )}

            <CounterDialog
                open={counterOpen}
                onClose={() => setCounterOpen(false)}
                onSubmit={sendCounter}
                processing={busy}
                title="Send a counter offer"
                description={<>Current offer is <strong>{currency.format(latestPrice)}</strong>. Enter your price.</>}
                fieldLabel="Your price (₱)"
                fieldKey="admin_counter"
            />

            <AcceptDialog
                open={acceptOpen}
                onClose={() => setAcceptOpen(false)}
                onConfirm={() => {
                    setAcceptOpen(false);
                    clientJustCountered ? agreeClientCounter() : decide('accepted');
                }}
                processing={busy}
                price={acceptPrice}
            />
        </div>
    );
}

// ─── Client proposal card ─────────────────────────────────────────────────────

function ClientProposalCard({ proposal }: { proposal: Proposal }) {
    const [counterOpen, setCounterOpen] = React.useState(false);
    const [busy, setBusy] = React.useState(false);

    const agree = () => {
        setBusy(true);
        router.patch(`/my-projects/${proposal.id}/agree-counter`, {}, { preserveScroll: true, onFinish: () => setBusy(false) });
    };

    const reject = () => {
        setBusy(true);
        router.patch(`/my-projects/${proposal.id}/reject`, {}, { preserveScroll: true, onFinish: () => setBusy(false) });
    };

    const sendClientCounter = (price: string) => {
        setBusy(true);
        router.patch(`/my-projects/${proposal.id}/client-counter`, { client_counter_price: price }, {
            preserveScroll: true,
            onFinish: () => { setBusy(false); setCounterOpen(false); },
        });
    };

    // Admin countered and it's now the client's turn
    // True when: status=countered AND last move was by admin (or null = legacy)
    const adminHasTheBall = proposal.approval_status === 'countered'
        && (proposal.last_counter_by === 'admin' || proposal.last_counter_by === null);

    // Client already countered — waiting for admin
    const clientHasTheBall = proposal.approval_status === 'countered'
        && proposal.last_counter_by === 'client';

    // The price the admin most recently put on the table
    const latestAdminPrice = proposal.admin_counter_price ?? null;

    return (
        <div className={`rounded-xl border p-4 ${proposal.approval_status === 'rejected' ? 'opacity-60' : ''}`}>
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <p className="font-semibold">{proposal.name}</p>
                        <StatusBadge status={proposal.approval_status} />
                    </div>
                    {proposal.description && <p className="mt-2 text-sm text-muted-foreground leading-relaxed">{proposal.description}</p>}
                </div>
                <div className="shrink-0 text-right space-y-1">
                    <p className="text-xs text-muted-foreground">Your price</p>
                    <p className={`text-xl font-bold ${latestAdminPrice ? 'line-through text-muted-foreground text-base' : ''}`}>
                        {currency.format(proposal.price)}
                    </p>
                    {latestAdminPrice && (
                        <>
                            <p className="text-xs text-muted-foreground">Admin's offer</p>
                            <p className="text-xl font-bold text-blue-600 dark:text-blue-400">{currency.format(latestAdminPrice)}</p>
                        </>
                    )}
                </div>
            </div>

            <NegotiationHistory proposal={proposal} />

            {/* ── Tax preview — always visible ── */}
            <TaxPreview price={latestAdminPrice ?? proposal.price} taxRate={proposal.default_tax_rate} />

            {/* Admin countered — client's turn */}
            {adminHasTheBall && (
                <div className="mt-4 rounded-lg border border-blue-200 bg-blue-50 p-3 dark:border-blue-800 dark:bg-blue-950">
                    <p className="text-sm font-medium text-blue-700 dark:text-blue-300">
                        Admin offered {currency.format(latestAdminPrice!)}. Agree, counter, or reject.
                    </p>
                    <div className="mt-3 flex flex-wrap gap-2">
                        <Button size="sm" disabled={busy} onClick={agree}>
                            Agree — {currency.format(latestAdminPrice!)}
                        </Button>
                        <Button size="sm" variant="outline" disabled={busy} onClick={() => setCounterOpen(true)}>
                            Counter price
                        </Button>
                        <Button size="sm" variant="destructive" disabled={busy} onClick={reject}>
                            Reject
                        </Button>
                    </div>
                </div>
            )}

            {/* Client already countered — waiting */}
            {clientHasTheBall && (
                <p className="mt-3 text-sm text-muted-foreground">
                    You countered with <span className="font-semibold">{currency.format(proposal.client_counter_price!)}</span>. Waiting for admin response.
                </p>
            )}

            {proposal.approval_status === 'pending' && (
                <p className="mt-3 text-sm text-muted-foreground">Waiting for admin review.</p>
            )}

            {proposal.approval_status === 'rejected' && (
                <p className="mt-3 text-sm text-destructive">This proposal was rejected.</p>
            )}

            <CounterDialog
                open={counterOpen}
                onClose={() => setCounterOpen(false)}
                onSubmit={sendClientCounter}
                processing={busy}
                title="Send your counter offer"
                description={<>Admin offered <strong>{currency.format(latestAdminPrice!)}</strong>. Enter your price.</>}
                fieldLabel="Your counter price (₱)"
                fieldKey="client_counter"
            />
        </div>
    );
}

// ─── Main page ────────────────────────────────────────────────────────────────

export default function Invoices({
    invoices,
    proposals,
    available_milestones,
    selected_milestone_id,
    invoice_pagination,
    invoice_counts,
    filters,
}: Props) {
    const { auth } = usePage<SharedData>().props;
    const isAdmin = auth.role === 'admin';
    const [searchValue, setSearchValue] = React.useState(filters.search);
    const initialMilestone = selected_milestone_id
        ? available_milestones.find(milestone => milestone.id === selected_milestone_id)
        : undefined;

    const [editing, setEditing] = React.useState<{ invoice: Invoice; payment: Payment } | null>(null);
    const form = useForm({ amount_paid: '', paid_at: '', method: 'bank_transfer' });
    const [createOpen, setCreateOpen] = React.useState(Boolean(initialMilestone));
    const [paymentInvoice, setPaymentInvoice] = React.useState<Invoice | null>(null);
    const createForm = useForm({
        milestone_id: initialMilestone ? String(initialMilestone.id) : '',
        tax_rate: initialMilestone ? String(initialMilestone.default_tax_rate * 100) : '12',
    });
    const paymentForm = useForm({ amount_paid: '', paid_at: new Date().toISOString().slice(0, 10), method: 'bank_transfer' });

    React.useEffect(() => setSearchValue(filters.search), [filters.search]);

    const openEditor = (invoice: Invoice, payment: Payment) => {
        setEditing({ invoice, payment });
        form.setData({
            amount_paid: formatTwoDecimals(String(payment.amount_paid)),
            paid_at: payment.paid_at?.slice(0, 10) ?? '',
            method: payment.method,
        });
    };

    const openCreate = () => {
        const first = available_milestones[0];
        createForm.setData({
            milestone_id: first ? String(first.id) : '',
            tax_rate: first ? String(first.default_tax_rate * 100) : '12',
        });
        setCreateOpen(true);
    };

    const openPayment = (invoice: Invoice) => {
        setPaymentInvoice(invoice);
        paymentForm.setData({
            amount_paid: invoice.balance_due > 0 ? invoice.balance_due.toFixed(2) : '',
            paid_at: new Date().toISOString().slice(0, 10),
            method: 'bank_transfer',
        });
    };

    const selectedMilestone = available_milestones.find(
        milestone => String(milestone.id) === createForm.data.milestone_id,
    );

    const createTaxRate = (createForm.data.tax_rate ? Number(createForm.data.tax_rate) : 0) / 100;
    const createTaxAmount = selectedMilestone
        ? Math.round(selectedMilestone.amount * createTaxRate * 100) / 100
        : 0;

    const goToInvoices = (
        page: number,
        status = filters.status,
        search = searchValue,
        invoiceId: number | null = filters.invoice_id,
    ) => {
        router.get('/invoices', {
            page,
            status,
            search: search || undefined,
            invoice_id: invoiceId ?? undefined,
        }, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    const active = proposals.filter(p => p.approval_status !== 'rejected');
    const closed = proposals.filter(p => p.approval_status === 'rejected');

    return (
        <AppLayout breadcrumbs={[{ title: 'Invoices', href: '/invoices' }]}>
            <Head title="Invoices" />
            <div className="flex flex-1 flex-col gap-6 bg-slate-50/60 p-4 md:p-6 dark:bg-background">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Invoices</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {isAdmin ? 'Negotiate project proposals and manage billing invoices.' : 'Your project proposals and billing invoices.'}
                    </p>
                </div>

                {/* ── Negotiation board ── */}
                <Card>
                    <CardHeader>
                        <CardTitle>{isAdmin ? 'Project proposals' : 'My proposals'}</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {proposals.length === 0 && (
                            <p className="text-sm text-muted-foreground">No proposals yet.</p>
                        )}
                        {active.map(p => isAdmin
                            ? <AdminProposalCard key={p.id} proposal={p} />
                            : <ClientProposalCard key={p.id} proposal={p} />
                        )}
                        {closed.length > 0 && (
                            <>
                                <p className="pt-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Closed</p>
                                {closed.map(p => isAdmin
                                    ? <AdminProposalCard key={p.id} proposal={p} />
                                    : <ClientProposalCard key={p.id} proposal={p} />
                                )}
                            </>
                        )}
                    </CardContent>
                </Card>

                {/* ── Billing invoices ── */}
                <Card>
                    <CardHeader className="flex-row items-center justify-between">
                        <div>
                            <CardTitle>Billing invoices</CardTitle>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {invoice_pagination.total} {filters.status !== 'all' || filters.search || filters.invoice_id ? 'matching ' : ''}
                                invoice{invoice_pagination.total === 1 ? '' : 's'} · newest first
                            </p>
                        </div>
                        {isAdmin && (
                            <Button type="button" size="sm" onClick={openCreate} disabled={available_milestones.length === 0}>
                                <Plus className="size-4" />
                                Create invoice
                            </Button>
                        )}
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex flex-col gap-3 md:flex-row">
                            <form
                                className="flex min-w-0 flex-1 gap-2"
                                onSubmit={event => {
                                    event.preventDefault();
                                    goToInvoices(1, filters.status, searchValue.trim(), null);
                                }}
                            >
                                <Input
                                    aria-label="Search invoices"
                                    placeholder="Search invoice #, client, project, or milestone"
                                    value={searchValue}
                                    onChange={event => setSearchValue(event.target.value)}
                                />
                                <Button type="submit" variant="outline" aria-label="Search">
                                    <Search className="size-4" />
                                    Search
                                </Button>
                            </form>
                            <div className="flex flex-wrap gap-2" aria-label="Filter invoices by status">
                                {([
                                    ['all', 'All'],
                                    ['unpaid', 'Unpaid'],
                                    ['partial', 'Partially paid'],
                                    ['paid', 'Paid'],
                                ] as const).map(([status, label]) => (
                                    <Button
                                        key={status}
                                        type="button"
                                        size="sm"
                                        variant={filters.status === status ? 'default' : 'outline'}
                                        onClick={() => goToInvoices(1, status, searchValue, null)}
                                    >
                                        {label} <span className="ml-1 opacity-75">{invoice_counts[status]}</span>
                                    </Button>
                                ))}
                            </div>
                        </div>
                        {filters.invoice_id && (
                            <div className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-800 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-200">
                                <span>Showing invoice #{filters.invoice_id} linked to its project milestone.</span>
                                <Button type="button" size="sm" variant="outline" onClick={() => goToInvoices(1, 'all', '', null)}>
                                    Show all invoices
                                </Button>
                            </div>
                        )}

                        {invoices.data.length === 0 ? (
                            <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                                {filters.search || filters.status !== 'all' || filters.invoice_id
                                    ? 'No invoices match these filters. Try another search or status.'
                                    : 'No invoices yet.'}
                            </p>
                        ) : invoices.data.map(invoice => (
                            <div key={invoice.id} id={`invoice-${invoice.id}`} className="rounded-lg border p-4">
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <p className="font-semibold">Invoice #{invoice.id}</p>
                                            <Badge variant={invoice.status === 'paid' ? 'secondary' : invoice.status === 'partial' ? 'outline' : 'destructive'}>
                                                {invoice.status === 'paid' ? 'Paid' : invoice.status === 'partial' ? 'Partially paid' : 'Unpaid'}
                                            </Badge>
                                        </div>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            {invoice.milestone?.project?.client?.company_name} · {invoice.milestone?.project?.name} · {invoice.milestone?.title}
                                        </p>
                                    </div>
                                    <div className="shrink-0 text-right">
                                        <p className="text-xs text-muted-foreground">Balance due</p>
                                        <p className={`text-xl font-bold ${invoice.balance_due <= 0 ? 'text-emerald-700 dark:text-emerald-400' : ''}`}>
                                            {currency.format(invoice.balance_due)}
                                        </p>
                                    </div>
                                </div>

                                <div className="mt-4 grid gap-4 border-t pt-4 md:grid-cols-2">
                                    <div className="space-y-1.5 text-sm">
                                        <div className="flex justify-between text-muted-foreground">
                                            <span>Subtotal</span><span>{currency.format(invoice.subtotal)}</span>
                                        </div>
                                        <div className="flex justify-between text-muted-foreground">
                                            <span>Tax ({((Number(invoice.tax_rate) || 0) * 100).toFixed(2)}%)</span>
                                            <span>{currency.format(Number(invoice.tax_amount))}</span>
                                        </div>
                                        <div className="flex justify-between border-t pt-2 font-semibold">
                                            <span>Total amount due</span>
                                            <span>{currency.format(Number(invoice.total_amount_due))}</span>
                                        </div>
                                    </div>
                                    <div className="space-y-1.5 text-sm">
                                        <div className="flex justify-between text-muted-foreground">
                                            <span>Amount paid</span>
                                            <span className="text-emerald-700 dark:text-emerald-400">{currency.format(invoice.amount_paid)}</span>
                                        </div>
                                        <div className="flex justify-between border-t pt-2 font-bold">
                                            <span>Remaining balance</span>
                                            <span>{currency.format(invoice.balance_due)}{invoice.balance_due <= 0 ? ' ✓' : ''}</span>
                                        </div>
                                    </div>
                                </div>

                                {isAdmin && invoice.balance_due > 0 && (
                                    <div className="mt-4">
                                        <Button type="button" size="sm" variant="outline" onClick={() => openPayment(invoice)}>
                                            <CreditCard className="size-4" />
                                            Record payment
                                        </Button>
                                    </div>
                                )}

                                {invoice.payments.length > 0 ? (
                                    <div className="mt-4 divide-y border-t">
                                        {invoice.payments.map(payment => (
                                            <div key={payment.id} className="flex items-center justify-between gap-3 py-3">
                                                <div>
                                                    <p className="text-sm font-medium">{currency.format(Number(payment.amount_paid))}</p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {(payment.method || 'bank_transfer').replace('_', ' ')} · {payment.paid_at?.slice(0, 10) || 'Date not set'}
                                                    </p>
                                                </div>
                                                {isAdmin && (
                                                    <Button type="button" variant="outline" size="sm" onClick={() => openEditor(invoice, payment)}>
                                                        <Pencil className="size-3.5" /> Edit
                                                    </Button>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <p className="mt-4 text-sm text-muted-foreground">No payments recorded yet.</p>
                                )}
                            </div>
                        ))}

                        {invoice_pagination.total > 0 && (
                            <div className="flex flex-col items-center justify-between gap-3 border-t pt-4 sm:flex-row">
                                <p className="text-sm text-muted-foreground">
                                    Showing <span className="font-medium">{invoice_pagination.from ?? 0}–{invoice_pagination.to ?? 0}</span>
                                    {' '}of <span className="font-medium">{invoice_pagination.total}</span> matching invoices
                                </p>
                                {invoice_pagination.last_page > 1 && (
                                    <div className="flex items-center gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            disabled={invoice_pagination.current_page <= 1}
                                            onClick={() => goToInvoices(invoice_pagination.current_page - 1)}
                                        >
                                            <ChevronLeft className="size-4" /> Previous
                                        </Button>
                                        <span className="min-w-20 text-center text-sm text-muted-foreground">
                                            Page {invoice_pagination.current_page} of {invoice_pagination.last_page}
                                        </span>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            disabled={invoice_pagination.current_page >= invoice_pagination.last_page}
                                            onClick={() => goToInvoices(invoice_pagination.current_page + 1)}
                                        >
                                            Next <ChevronRight className="size-4" />
                                        </Button>
                                    </div>
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* ── Create invoice dialog (admin only) ── */}
                {isAdmin && (
                    <Dialog open={createOpen} onOpenChange={open => !open && setCreateOpen(false)}>
                        <DialogContent className="sm:max-w-md">
                            <DialogHeader>
                                <DialogTitle>Create invoice</DialogTitle>
                                <DialogDescription>Select an uninvoiced milestone and set the tax rate.</DialogDescription>
                            </DialogHeader>
                            <form
                                className="space-y-4"
                                onSubmit={e => {
                                    e.preventDefault();
                                    createForm.post('/invoices', {
                                        preserveScroll: true,
                                        onSuccess: () => setCreateOpen(false),
                                    });
                                }}
                            >
                                <div className="space-y-2">
                                    <Label htmlFor="invoice_milestone">Milestone</Label>
                                    <select
                                        id="invoice_milestone"
                                        className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                        value={createForm.data.milestone_id}
                                        onChange={e => {
                                            const milestone = available_milestones.find(m => m.id === Number(e.target.value));
                                            createForm.setData({
                                                milestone_id: e.target.value,
                                                tax_rate: milestone ? String(milestone.default_tax_rate * 100) : createForm.data.tax_rate,
                                            });
                                        }}
                                    >
                                        {available_milestones.map(milestone => (
                                            <option key={milestone.id} value={milestone.id}>
                                                {milestone.client_name ? `${milestone.client_name} · ` : ''}
                                                {milestone.project_name} — {milestone.title} ({currency.format(milestone.amount)})
                                            </option>
                                        ))}
                                    </select>
                                    {createForm.errors.milestone_id && <p className="text-sm text-destructive">{createForm.errors.milestone_id}</p>}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="invoice_tax_rate">Tax rate (%)</Label>
                                    <Input
                                        id="invoice_tax_rate"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        value={createForm.data.tax_rate}
                                        onChange={e => createForm.setData('tax_rate', e.target.value)}
                                    />
                                    {createForm.errors.tax_rate && <p className="text-sm text-destructive">{createForm.errors.tax_rate}</p>}
                                </div>
                                {selectedMilestone && (
                                    <div className="rounded-lg border bg-muted/40 px-4 py-3 text-sm space-y-1.5">
                                        <div className="flex justify-between text-muted-foreground">
                                            <span>Subtotal</span><span>{currency.format(selectedMilestone.amount)}</span>
                                        </div>
                                        <div className="flex justify-between text-muted-foreground">
                                            <span>Tax ({(createTaxRate * 100).toFixed(2)}%)</span><span>{currency.format(createTaxAmount)}</span>
                                        </div>
                                        <div className="flex justify-between border-t pt-1.5 font-semibold">
                                            <span>Total amount due</span>
                                            <span>{currency.format(selectedMilestone.amount + createTaxAmount)}</span>
                                        </div>
                                    </div>
                                )}
                                <Button type="submit" className="w-full" disabled={createForm.processing || available_milestones.length === 0}>
                                    {createForm.processing ? 'Creating...' : 'Create invoice'}
                                </Button>
                            </form>
                        </DialogContent>
                    </Dialog>
                )}

                {/* ── Record payment dialog (admin only) ── */}
                {isAdmin && (
                    <Dialog open={Boolean(paymentInvoice)} onOpenChange={open => !open && setPaymentInvoice(null)}>
                        <DialogContent className="sm:max-w-md">
                            <DialogHeader>
                                <DialogTitle>Record payment</DialogTitle>
                                <DialogDescription>
                                    {paymentInvoice ? `Invoice #${paymentInvoice.id} — balance due ${currency.format(paymentInvoice.balance_due)}` : ''}
                                </DialogDescription>
                            </DialogHeader>
                            {paymentInvoice && (
                                <form
                                    className="space-y-4"
                                    onSubmit={e => {
                                        e.preventDefault();
                                        paymentForm.post(`/invoices/${paymentInvoice.id}/payments`, {
                                            preserveScroll: true,
                                            onSuccess: () => setPaymentInvoice(null),
                                        });
                                    }}
                                >
                                    <div className="space-y-2">
                                        <Label htmlFor="record_amount_paid">Amount</Label>
                                        <Input
                                            id="record_amount_paid"
                                            type="number"
                                            min="0.01"
                                            step="0.01"
                                            max={paymentInvoice.balance_due}
                                            value={paymentForm.data.amount_paid}
                                            onChange={e => paymentForm.setData('amount_paid', limitToTwoDecimals(e.target.value))}
                                            onBlur={() => paymentForm.setData('amount_paid', formatTwoDecimals(paymentForm.data.amount_paid))}
                                        />
                                        {paymentForm.errors.amount_paid && <p className="text-sm text-destructive">{paymentForm.errors.amount_paid}</p>}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="record_paid_at">Payment date</Label>
                                        <Input
                                            id="record_paid_at"
                                            type="date"
                                            value={paymentForm.data.paid_at}
                                            onChange={e => paymentForm.setData('paid_at', e.target.value)}
                                        />
                                        {paymentForm.errors.paid_at && <p className="text-sm text-destructive">{paymentForm.errors.paid_at}</p>}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="record_method">Payment method</Label>
                                        <select
                                            id="record_method"
                                            className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                            value={paymentForm.data.method}
                                            onChange={e => paymentForm.setData('method', e.target.value)}
                                        >
                                            <option value="bank_transfer">Bank transfer</option>
                                            <option value="gcash">GCash</option>
                                            <option value="cash">Cash</option>
                                            <option value="check">Check</option>
                                            <option value="paypal">PayPal</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                    <Button type="submit" className="w-full" disabled={paymentForm.processing}>
                                        {paymentForm.processing ? 'Recording...' : 'Record payment'}
                                    </Button>
                                </form>
                            )}
                        </DialogContent>
                    </Dialog>
                )}

                {/* ── Edit payment dialog (admin only) ── */}
                <Dialog open={Boolean(editing)} onOpenChange={open => !open && setEditing(null)}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Edit payment</DialogTitle>
                            <DialogDescription>Update the amount, date, or payment method.</DialogDescription>
                        </DialogHeader>
                        {editing && (
                            <form className="space-y-4" onSubmit={e => {
                                e.preventDefault();
                                form.patch(`/invoices/${editing.invoice.id}/payments/${editing.payment.id}`, {
                                    preserveScroll: true, onSuccess: () => setEditing(null),
                                });
                            }}>
                                <div className="space-y-2">
                                    <Label htmlFor="amount_paid">Amount</Label>
                                    <Input
                                        id="amount_paid"
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        value={form.data.amount_paid}
                                        onChange={e => form.setData('amount_paid', limitToTwoDecimals(e.target.value))}
                                        onBlur={() => form.setData('amount_paid', formatTwoDecimals(form.data.amount_paid))}
                                    />
                                    {form.errors.amount_paid && <p className="text-sm text-destructive">{form.errors.amount_paid}</p>}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="paid_at">Payment date</Label>
                                    <Input id="paid_at" type="date" value={form.data.paid_at} onChange={e => form.setData('paid_at', e.target.value)} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="method">Method</Label>
                                    <Input id="method" value={form.data.method} onChange={e => form.setData('method', e.target.value)} />
                                </div>
                                <Button type="submit" className="w-full" disabled={form.processing}>
                                    {form.processing ? 'Saving...' : 'Save payment'}
                                </Button>
                            </form>
                        )}
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
