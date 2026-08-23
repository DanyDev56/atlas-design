import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { listInvoices, listQuotes } from '@/api/billing';
import { listClients } from '@/api/crm';
import { ErrorBanner } from '@/components/auth/AuthLayout';
import { StatusBadge } from '@/components/crm/StatusBadge';
import { RequireAuth } from '@/components/layout/RequireAuth';
import { EmptyState } from '@/components/ui/EmptyState';
import { PageSkeleton } from '@/components/ui/PageSkeleton';
import { PageHeader } from '@/components/ui/PageHeader';
import { Icon } from '@/components/ui/Icon';
import { useAuth } from '@/hooks/useAuth';
import type { ClientSummary, InvoiceSummary, QuoteSummary } from '@/types/api';
import { formatMoney, invoiceDisplayStatus } from '@/utils/format';

export function BillingPage() {
    const { session } = useAuth();
    const token = session!.token;
    const workspaceId = session!.workspaceId!;
    const [quotes, setQuotes] = useState<QuoteSummary[]>([]);
    const [invoices, setInvoices] = useState<InvoiceSummary[]>([]);
    const [clients, setClients] = useState<ClientSummary[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    async function loadBilling() {
        setLoading(true);
        setError(null);
        try {
            const [quoteData, invoiceData, clientData] = await Promise.all([
                listQuotes(token, workspaceId),
                listInvoices(token, workspaceId),
                listClients(token, workspaceId).catch(() => [] as ClientSummary[]),
            ]);
            setQuotes(quoteData);
            setInvoices(invoiceData);
            setClients(clientData);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Chargement de la facturation impossible');
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => {
        void loadBilling();
    }, [token, workspaceId]);

    const clientNames = new Map(clients.map((client) => [client.client_id, client.display_name]));
    const invoicesToCollect = invoices.filter((invoice) => invoice.balance_cents > 0).length;
    const quotesInProgress = quotes.filter((quote) => ['Draft', 'Sent'].includes(quote.status)).length;
    const settledInvoices = invoices.filter((invoice) => invoice.balance_cents === 0).length;

    return (
        <RequireAuth>
            <div className="atlas-page max-w-5xl">
                <PageHeader
                    eyebrow="Revenus & encaissements"
                    title="Facturation"
                    description="Suivez vos devis, vos factures émises et les montants qui restent à encaisser."
                    actions={<div className="flex flex-wrap gap-3">
                        <Link
                            to="/app/billing/import"
                            className="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-atlas-border bg-white px-4 py-2.5 text-sm font-semibold text-atlas-ink shadow-sm hover:border-atlas-accent/30 hover:bg-atlas-surface"
                        >
                            <Icon name="upload" className="size-4 text-atlas-ink-muted" />
                            Importer un historique
                        </Link>
                        <Link
                            to="/app/crm"
                            className="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-atlas-accent px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#066557]"
                        >
                            <Icon name="plus" className="size-4" />
                            Créer depuis le CRM
                        </Link>
                    </div>}
                />

                {!loading && !error && (quotes.length > 0 || invoices.length > 0) && (
                    <dl className="mb-8 grid overflow-hidden rounded-2xl border border-atlas-border bg-white/75 shadow-sm sm:grid-cols-3 sm:divide-x sm:divide-atlas-border">
                        {[
                            ['À encaisser', invoicesToCollect, 'factures avec un solde'],
                            ['Devis en cours', quotesInProgress, 'à préparer ou en attente'],
                            ['Soldées', settledInvoices, 'factures encaissées'],
                        ].map(([label, value, detail]) => (
                            <div key={label} className="px-5 py-4 not-first:border-t not-first:border-atlas-border sm:not-first:border-t-0">
                                <dt className="text-[11px] font-semibold uppercase tracking-[0.12em] text-atlas-ink-muted">{label}</dt>
                                <dd className="mt-1 flex items-baseline gap-2">
                                    <span className="text-2xl font-semibold tabular-nums tracking-tight text-atlas-ink">{value}</span>
                                    <span className="text-xs text-atlas-ink-muted">{detail}</span>
                                </dd>
                            </div>
                        ))}
                    </dl>
                )}

                {error && (
                    <div className="mt-6">
                        <ErrorBanner message={error} />
                        <button
                            type="button"
                            onClick={() => void loadBilling()}
                            className="text-sm font-semibold text-atlas-accent hover:underline"
                        >
                            Réessayer
                        </button>
                    </div>
                )}

                {loading && <div className="mt-8"><PageSkeleton rows={3} /></div>}

                {!loading && !error && quotes.length === 0 && invoices.length === 0 && (
                    <div className="mt-8">
                        <EmptyState
                            title="Aucun devis pour l’instant"
                            description="Qualifiez une opportunité dans le CRM, puis préparez le premier devis de votre activité."
                            action={
                                <Link
                                    to="/app/crm"
                                    className="rounded-xl bg-atlas-accent px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90"
                                >
                                    Ouvrir le CRM
                                </Link>
                            }
                        />
                    </div>
                )}

                {!loading && !error && (quotes.length > 0 || invoices.length > 0) && (
                    <div className="mt-8 space-y-10">
                        <section aria-labelledby="invoices-title">
                            <div className="flex items-end justify-between gap-4">
                                <div>
                                    <h3 id="invoices-title" className="text-lg font-semibold text-atlas-ink">Factures</h3>
                                    <p className="mt-1 text-sm text-atlas-ink-muted">Les factures à émettre, envoyer ou encaisser.</p>
                                </div>
                                <span className="text-sm font-medium text-atlas-ink-muted">{invoices.length}</span>
                            </div>

                            {invoices.length === 0 ? (
                                <div className="mt-4 rounded-2xl border border-dashed border-atlas-border bg-atlas-card px-6 py-8 text-center">
                                    <p className="text-sm text-atlas-ink-muted">
                                        Une facture pourra être créée dès qu’un client aura accepté son devis.
                                    </p>
                                </div>
                            ) : (
                                <ul className="mt-4 divide-y divide-atlas-border overflow-hidden rounded-2xl border border-atlas-border bg-atlas-card shadow-sm">
                                    {invoices.map((invoice) => {
                                        const status = invoiceDisplayStatus(invoice);

                                        return (
                                            <li key={invoice.invoice_id}>
                                                <Link
                                                    to={`/app/billing/invoices/${invoice.invoice_id}`}
                                                    className="flex min-h-20 flex-wrap items-center justify-between gap-4 px-5 py-4 transition-colors hover:bg-slate-50 sm:px-6"
                                                >
                                                    <div className="flex min-w-0 items-center gap-3.5">
                                                        <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-indigo-50 text-indigo-600">
                                                            <Icon name="billing" className="size-[18px]" />
                                                        </span>
                                                        <div className="min-w-0">
                                                            <p className="truncate font-semibold text-atlas-ink">
                                                                {invoice.kind === 'Deposit' ? 'Acompte' : 'Facture'}{' '}
                                                                {invoice.invoice_number ?? invoice.invoice_id.slice(0, 8).toUpperCase()}
                                                            </p>
                                                            <p className="mt-1 text-xs text-atlas-ink-muted">
                                                                {clientNames.get(invoice.client_id) ?? 'Facture client'}
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <div className="flex items-center gap-4">
                                                        <StatusBadge status={status} />
                                                        <div className="min-w-28 text-right">
                                                            <p className="text-sm font-semibold tabular-nums text-atlas-ink">
                                                                {formatMoney(invoice.total_cents, invoice.currency)}
                                                            </p>
                                                            {invoice.balance_cents > 0 && invoice.balance_cents !== invoice.total_cents && (
                                                                <p className="mt-1 text-xs text-atlas-ink-muted">
                                                                    Reste {formatMoney(invoice.balance_cents, invoice.currency)}
                                                                </p>
                                                            )}
                                                        </div>
                                                        <span aria-hidden="true" className="text-atlas-ink-muted">→</span>
                                                    </div>
                                                </Link>
                                            </li>
                                        );
                                    })}
                                </ul>
                            )}
                        </section>

                        <section aria-labelledby="quotes-title">
                            <div className="flex items-end justify-between gap-4">
                                <div>
                                    <h3 id="quotes-title" className="text-lg font-semibold text-atlas-ink">Devis</h3>
                                    <p className="mt-1 text-sm text-atlas-ink-muted">Vérifiez chaque devis avant son envoi.</p>
                                </div>
                                <span className="text-sm font-medium text-atlas-ink-muted">{quotes.length}</span>
                            </div>
                            {quotes.length === 0 ? (
                                <div className="mt-4 rounded-2xl border border-dashed border-atlas-border bg-atlas-card px-6 py-8 text-center">
                                    <p className="text-sm text-atlas-ink-muted">Aucun devis dans cet espace de travail.</p>
                                </div>
                            ) : (
                                <ul className="mt-4 divide-y divide-atlas-border overflow-hidden rounded-2xl border border-atlas-border bg-atlas-card shadow-sm">
                                    {quotes.map((quote) => (
                                        <li key={quote.quote_id}>
                                            <Link
                                                to={`/app/billing/quotes/${quote.quote_id}`}
                                                state={{ from: 'billing' }}
                                                className="flex min-h-20 flex-wrap items-center justify-between gap-4 px-5 py-4 transition-colors hover:bg-slate-50 sm:px-6"
                                            >
                                                <div className="flex min-w-0 items-center gap-3.5">
                                                    <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-atlas-accent-soft text-atlas-accent">
                                                        <Icon name="billing" className="size-[18px]" />
                                                    </span>
                                                    <div className="min-w-0">
                                                        <p className="truncate font-semibold text-atlas-ink">
                                                            {clientNames.get(quote.client_id) ?? 'Devis client'}
                                                        </p>
                                                        <p className="mt-1 text-xs text-atlas-ink-muted">
                                                            Devis {quote.quote_id.slice(0, 8).toUpperCase()}
                                                        </p>
                                                    </div>
                                                </div>
                                                <div className="flex items-center gap-4">
                                                    <StatusBadge status={quote.status} />
                                                    <p className="min-w-24 text-right text-sm font-semibold tabular-nums text-atlas-ink">
                                                        {formatMoney(quote.total_cents, quote.currency)}
                                                    </p>
                                                    <span aria-hidden="true" className="text-atlas-ink-muted">→</span>
                                                </div>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    </div>
                )}
            </div>
        </RequireAuth>
    );
}
