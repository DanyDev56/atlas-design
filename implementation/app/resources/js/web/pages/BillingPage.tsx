import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { listQuotes } from '@/api/billing';
import { listClients } from '@/api/crm';
import { ErrorBanner } from '@/components/auth/AuthLayout';
import { StatusBadge } from '@/components/crm/StatusBadge';
import { RequireAuth } from '@/components/layout/RequireAuth';
import { EmptyState } from '@/components/ui/EmptyState';
import { PageSkeleton } from '@/components/ui/PageSkeleton';
import { useAuth } from '@/hooks/useAuth';
import type { ClientSummary, QuoteSummary } from '@/types/api';
import { formatMoney } from '@/utils/format';

export function BillingPage() {
    const { session } = useAuth();
    const token = session!.token;
    const workspaceId = session!.workspaceId!;
    const [quotes, setQuotes] = useState<QuoteSummary[]>([]);
    const [clients, setClients] = useState<ClientSummary[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    async function loadBilling() {
        setLoading(true);
        setError(null);
        try {
            const quoteData = await listQuotes(token, workspaceId);
            setQuotes(quoteData);

            try {
                setClients(await listClients(token, workspaceId));
            } catch {
                setClients([]);
            }
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
                        <h2 className="mt-2 text-3xl font-semibold tracking-tight text-atlas-ink">Vos devis</h2>
                        <p className="mt-2 max-w-2xl text-sm leading-relaxed text-atlas-ink-muted">
                            Vérifiez les lignes et le montant de chaque devis avant de l’envoyer au client.
                        </p>
                    </div>
                    <Link
                        to="/app/crm"
                        className="inline-flex min-h-11 items-center justify-center rounded-xl border border-atlas-border bg-white px-4 py-2.5 text-sm font-semibold text-atlas-ink hover:bg-slate-50"
                    >
                        Créer depuis le CRM
                    </Link>
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

                {!loading && !error && quotes.length === 0 && (
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

                {!loading && !error && quotes.length > 0 && (
                    <ul className="mt-8 divide-y divide-atlas-border overflow-hidden rounded-2xl border border-atlas-border bg-atlas-card shadow-sm">
                        {quotes.map((quote) => (
                            <li key={quote.quote_id}>
                                <Link
                                    to={`/app/billing/quotes/${quote.quote_id}`}
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
            </div>
        </RequireAuth>
    );
}
