import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { listInvoices, listQuotes } from '@/api/billing';
import { listClients } from '@/api/crm';
import { ErrorBanner } from '@/components/auth/AuthLayout';
import { StatusBadge } from '@/components/crm/StatusBadge';
import { RequireAuth } from '@/components/layout/RequireAuth';
import { EmptyState } from '@/components/ui/EmptyState';
import { PageSkeleton } from '@/components/ui/PageSkeleton';
import { useAuth } from '@/hooks/useAuth';
import type { ClientSummary, InvoiceSummary, QuoteSummary } from '@/types/api';
import { formatMoney } from '@/utils/format';

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

    return (
        <RequireAuth>
            <div className="mx-auto max-w-5xl">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-[0.18em] text-atlas-accent">
                            Facturation
                        </p>
                        <h2 className="mt-2 text-3xl font-semibold tracking-tight text-atlas-ink">Facturation</h2>
                        <p className="mt-2 max-w-2xl text-sm leading-relaxed text-atlas-ink-muted">
                            Suivez vos devis, vos factures émises et les montants qui restent à encaisser.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-3">
                        <Link
                            to="/app/billing/import"
                            className="inline-flex min-h-11 items-center justify-center rounded-xl border border-atlas-border bg-white px-4 py-2.5 text-sm font-semibold text-atlas-ink hover:bg-slate-50"
                        >
                            Importer un historique
                        </Link>
                        <Link
                            to="/app/crm"
                            className="inline-flex min-h-11 items-center justify-center rounded-xl border border-atlas-border bg-white px-4 py-2.5 text-sm font-semibold text-atlas-ink hover:bg-slate-50"
                        >
                            Créer depuis le CRM
                        </Link>
                    </div>
                </div>

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
                                        const status = invoice.settlement_status !== 'Unpaid'
                                            ? invoice.settlement_status
                                            : invoice.status;

                                        return (
                                            <li key={invoice.invoice_id}>
                                                <Link
                                                    to={`/app/billing/invoices/${invoice.invoice_id}`}
                                                    className="flex min-h-20 flex-wrap items-center justify-between gap-4 px-5 py-4 transition-colors hover:bg-slate-50 sm:px-6"
                                                >
                                                    <div className="min-w-0">
                                                        <p className="truncate font-medium text-atlas-ink">
                                                            {invoice.invoice_number ?? `Facture ${invoice.invoice_id.slice(0, 8).toUpperCase()}`}
                                                        </p>
                                                        <p className="mt-1 text-xs text-atlas-ink-muted">
                                                            {clientNames.get(invoice.client_id) ?? 'Facture client'}
                                                        </p>
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
                                                <div className="min-w-0">
                                                    <p className="truncate font-medium text-atlas-ink">
                                                        {clientNames.get(quote.client_id) ?? 'Devis client'}
                                                    </p>
                                                    <p className="mt-1 text-xs text-atlas-ink-muted">
                                                        Devis {quote.quote_id.slice(0, 8).toUpperCase()}
                                                    </p>
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
