import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { Building2, Mail } from 'lucide-react';

type Billing = {
    subtotal:  number;
    tax:       number;
    total_due: number;
    paid:      number;
    balance:   number;
};

type Client = {
    id: number;
    company_name: string;
    email: string;
    projects_count: number;
    billing: Billing;
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Clients', href: '/clients' }];

const currency = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'PHP' });

export default function Clients({ clients }: { clients: Client[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Clients" />
            <div className="flex flex-1 flex-col gap-6 bg-slate-50/60 p-4 md:p-6 dark:bg-background">

                {/* ── Header ── */}
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Clients</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        All client records with their billing totals.
                    </p>
                </div>

                {/* ── Table ── */}
                <div className="overflow-hidden rounded-xl border bg-background shadow-sm">
                    {clients.length === 0 ? (
                        <div className="flex flex-col items-center gap-3 py-16 text-center">
                            <Building2 className="size-8 text-muted-foreground" />
                            <p className="font-medium">No clients yet</p>
                            <p className="text-sm text-muted-foreground">Clients will appear here once added.</p>
                        </div>
                    ) : (
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b bg-muted/50 text-xs uppercase tracking-wide text-muted-foreground">
                                    <th className="px-4 py-3 text-left">Client</th>
                                    <th className="px-4 py-3 text-center">Projects</th>
                                    <th className="px-4 py-3 text-right">Subtotal</th>
                                    <th className="px-4 py-3 text-right">Tax</th>
                                    <th className="px-4 py-3 text-right">Total due</th>
                                    <th className="px-4 py-3 text-right">Paid</th>
                                    <th className="px-4 py-3 text-right">Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                {clients.map((client) => {
                                    const fullyPaid = client.billing.total_due > 0 && client.billing.balance <= 0;
                                    return (
                                        <tr key={client.id} className="border-b last:border-0 transition-colors hover:bg-muted/30">
                                            {/* Client info */}
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-2 font-medium">
                                                    <Building2 className="size-4 shrink-0 text-muted-foreground" />
                                                    <span>{client.company_name}</span>
                                                </div>
                                                <div className="mt-0.5 flex items-center gap-2 text-xs text-muted-foreground">
                                                    <Mail className="size-3 shrink-0" />
                                                    <span>{client.email}</span>
                                                </div>
                                            </td>

                                            {/* Projects */}
                                            <td className="px-4 py-3 text-center text-muted-foreground">
                                                {client.projects_count}
                                            </td>

                                            {/* Subtotal */}
                                            <td className="px-4 py-3 text-right text-muted-foreground">
                                                {currency.format(client.billing.subtotal)}
                                            </td>

                                            {/* Tax */}
                                            <td className="px-4 py-3 text-right text-muted-foreground">
                                                {currency.format(client.billing.tax)}
                                            </td>

                                            {/* Total due */}
                                            <td className="px-4 py-3 text-right font-semibold">
                                                {currency.format(client.billing.total_due)}
                                            </td>

                                            {/* Paid */}
                                            <td className="px-4 py-3 text-right text-emerald-600 dark:text-emerald-400">
                                                {currency.format(client.billing.paid)}
                                            </td>

                                            {/* Balance */}
                                            <td className="px-4 py-3 text-right font-bold">
                                                {client.billing.total_due === 0 ? (
                                                    <span className="text-muted-foreground">—</span>
                                                ) : fullyPaid ? (
                                                    <span className="text-emerald-600 dark:text-emerald-400">✓ Paid</span>
                                                ) : (
                                                    <span>{currency.format(client.billing.balance)}</span>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>

                            {/* ── Totals footer ── */}
                            {clients.length > 1 && (() => {
                                const totals = clients.reduce(
                                    (acc, c) => ({
                                        subtotal:  acc.subtotal  + c.billing.subtotal,
                                        tax:       acc.tax       + c.billing.tax,
                                        total_due: acc.total_due + c.billing.total_due,
                                        paid:      acc.paid      + c.billing.paid,
                                        balance:   acc.balance   + c.billing.balance,
                                    }),
                                    { subtotal: 0, tax: 0, total_due: 0, paid: 0, balance: 0 }
                                );
                                return (
                                    <tfoot>
                                        <tr className="border-t bg-muted/40 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                            <td className="px-4 py-3">All clients</td>
                                            <td className="px-4 py-3 text-center">{clients.reduce((s, c) => s + c.projects_count, 0)}</td>
                                            <td className="px-4 py-3 text-right text-foreground">{currency.format(totals.subtotal)}</td>
                                            <td className="px-4 py-3 text-right text-foreground">{currency.format(totals.tax)}</td>
                                            <td className="px-4 py-3 text-right text-foreground">{currency.format(totals.total_due)}</td>
                                            <td className="px-4 py-3 text-right text-emerald-600 dark:text-emerald-400">{currency.format(totals.paid)}</td>
                                            <td className="px-4 py-3 text-right text-foreground">{currency.format(Math.max(totals.balance, 0))}</td>
                                        </tr>
                                    </tfoot>
                                );
                            })()}
                        </table>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
