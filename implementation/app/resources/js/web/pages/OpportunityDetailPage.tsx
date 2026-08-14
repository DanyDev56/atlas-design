import { FormEvent, useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { createQuote, listQuotes } from '@/api/billing';
import { getClient, getOpportunity, qualifyOpportunity } from '@/api/crm';
import { StatusBadge } from '@/components/crm/StatusBadge';
import { RequireAuth } from '@/components/layout/RequireAuth';
import { ErrorBanner, FormField, SubmitButton, SuccessBanner, inputClassName } from '@/components/auth/AuthLayout';
import { useAuth } from '@/hooks/useAuth';
import type { ClientDetail, OpportunityDetail, QuoteSummary } from '@/types/api';
import { formatMoney } from '@/utils/format';

export function OpportunityDetailPage() {
    const { opportunityId } = useParams<{ opportunityId: string }>();
    const navigate = useNavigate();
    const { session } = useAuth();
    const token = session!.token;
    const workspaceId = session!.workspaceId!;

    const [opportunity, setOpportunity] = useState<OpportunityDetail | null>(null);
    const [client, setClient] = useState<ClientDetail | null>(null);
    const [quotes, setQuotes] = useState<QuoteSummary[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [actionLoading, setActionLoading] = useState(false);
    const [showQuoteForm, setShowQuoteForm] = useState(false);
    const [lineDescription, setLineDescription] = useState('');
    const [lineAmount, setLineAmount] = useState('');
    const [success, setSuccess] = useState<string | null>(null);

    async function reload() {
        if (!opportunityId) return;

        setLoading(true);
        setError(null);
        try {
            const opp = await getOpportunity(token, workspaceId, opportunityId);
            const [clientData, allQuotes] = await Promise.all([
                getClient(token, workspaceId, opp.client_id),
                listQuotes(token, workspaceId),
            ]);
            setOpportunity(opp);
            setClient(clientData);
            setQuotes(allQuotes.filter((q) => q.opportunity_id === opportunityId));
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Chargement impossible');
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => {
        void reload();
    }, [token, workspaceId, opportunityId]);

    async function onQualify() {
        if (!opportunity) return;
        setActionLoading(true);
        setError(null);
        setSuccess(null);
        try {
            await qualifyOpportunity(token, workspaceId, opportunity.opportunity_id, opportunity.version);
            await reload();
            setSuccess('L’opportunité est qualifiée. Vous pouvez maintenant préparer un devis.');
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Qualification impossible');
        } finally {
            setActionLoading(false);
        }
    }

    async function onCreateQuote(event: FormEvent) {
        event.preventDefault();
        if (!opportunity) return;

        const unitPriceCents = Math.round(parseFloat(lineAmount.replace(',', '.')) * 100);
        if (!Number.isFinite(unitPriceCents) || unitPriceCents < 0) {
            setError('Montant invalide');
            return;
        }

        setActionLoading(true);
        setError(null);
        setSuccess(null);
        try {
            const result = await createQuote(token, workspaceId, {
                client_id: opportunity.client_id,
                opportunity_id: opportunity.opportunity_id,
                currency: opportunity.currency,
                lines: [{ description: lineDescription, quantity: 1, unit_price_cents: unitPriceCents }],
            });
            navigate(`/app/billing/quotes/${result.quote_id}`);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Création du devis impossible');
        } finally {
            setActionLoading(false);
        }
    }

    if (!opportunityId) {
        return (
            <RequireAuth>
                <p className="text-sm text-red-800">Opportunité introuvable.</p>
            </RequireAuth>
        );
    }

    return (
        <RequireAuth>
            <div className="mx-auto max-w-4xl">
                {client && (
                    <Link
                        to={`/app/crm/clients/${client.client_id}`}
                        className="text-sm font-medium text-atlas-accent hover:underline"
                    >
                        ← {client.display_name}
                    </Link>
                )}

                {loading && <div className="mt-8 h-40 animate-pulse rounded-2xl bg-white shadow-sm" />}

                {error && (
                    <div className="mt-6">
                        <ErrorBanner message={error} />
                    </div>
                )}

                {success && (
                    <div className="mt-6">
                        <SuccessBanner message={success} />
                    </div>
                )}

                {opportunity && client && !loading && (
                    <>
                        <div className="mt-6 flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h2 className="text-3xl font-semibold tracking-tight text-atlas-ink">
                                    {opportunity.title}
                                </h2>
                                <p className="mt-2 text-sm text-atlas-ink-muted">
                                    Client : {client.display_name}
                                    {opportunity.estimated_amount_cents != null &&
                                        ` · ${formatMoney(opportunity.estimated_amount_cents, opportunity.currency)}`}
                                </p>
                            </div>
                            <StatusBadge status={opportunity.status} />
                        </div>

                        {opportunity.status === 'Open' && (
                            <div className="mt-6 rounded-xl border border-atlas-border bg-atlas-card px-4 py-4">
                                <p className="text-sm text-atlas-ink-muted">
                                    Qualifiez l'opportunité avant d'envoyer un devis au client.
                                </p>
                                <button
                                    type="button"
                                    disabled={actionLoading}
                                    onClick={() => void onQualify()}
                                    className="mt-3 rounded-xl bg-atlas-accent px-4 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60"
                                >
                                    Qualifier l'opportunité
                                </button>
                            </div>
                        )}

                        <section className="mt-10">
                            <div className="mb-4 flex items-center justify-between gap-4">
                                <h3 className="text-lg font-semibold text-atlas-ink">Devis</h3>
                                {!showQuoteForm && opportunity.status === 'Qualified' && (
                                    <button
                                        type="button"
                                        onClick={() => setShowQuoteForm(true)}
                                        className="rounded-xl bg-atlas-accent px-4 py-2 text-sm font-semibold text-white hover:opacity-90"
                                    >
                                        Nouveau devis
                                    </button>
                                )}
                            </div>

                            {showQuoteForm && (
                                <form
                                    onSubmit={onCreateQuote}
                                    className="mb-6 rounded-2xl border border-atlas-border bg-atlas-card p-6 shadow-sm"
                                >
                                    <FormField label="Description de la ligne">
                                        <input
                                            required
                                            className={inputClassName}
                                            value={lineDescription}
                                            onChange={(e) => setLineDescription(e.target.value)}
                                            placeholder="Prestation conseil"
                                        />
                                    </FormField>
                                    <div className="mt-4">
                                        <FormField label="Montant (€)">
                                            <input
                                                required
                                                inputMode="decimal"
                                                className={inputClassName}
                                                value={lineAmount}
                                                onChange={(e) => setLineAmount(e.target.value)}
                                                placeholder="500"
                                            />
                                        </FormField>
                                    </div>
                                    <div className="mt-4 flex flex-col gap-3 sm:flex-row">
                                        <SubmitButton loading={actionLoading} loadingLabel="Création du devis…">
                                            Créer le devis
                                        </SubmitButton>
                                        <button
                                            type="button"
                                            onClick={() => setShowQuoteForm(false)}
                                            className="rounded-xl border border-atlas-border px-4 py-3 text-sm font-medium text-atlas-ink-muted"
                                        >
                                            Annuler
                                        </button>
                                    </div>
                                </form>
                            )}

                            {quotes.length === 0 && (
                                <div className="rounded-2xl border border-dashed border-atlas-border bg-atlas-card px-6 py-10 text-center">
                                    <p className="text-sm text-atlas-ink-muted">
                                        Aucun devis. Créez un brouillon puis envoyez-le au client.
                                    </p>
                                </div>
                            )}

                            {quotes.length > 0 && (
                                <ul className="divide-y divide-atlas-border overflow-hidden rounded-2xl border border-atlas-border bg-atlas-card shadow-sm">
                                    {quotes.map((quote) => (
                                        <li
                                            key={quote.quote_id}
                                            className="flex flex-wrap items-center justify-between gap-4 px-6 py-4"
                                        >
                                            <div>
                                                <p className="font-medium text-atlas-ink">
                                                    {formatMoney(quote.total_cents, quote.currency)}
                                                </p>
                                                <p className="mt-0.5 font-mono text-xs text-atlas-ink-muted">
                                                    {quote.quote_id.slice(0, 8)}…
                                                </p>
                                            </div>
                                            <div className="flex items-center gap-3">
                                                <StatusBadge status={quote.status} />
                                                <Link
                                                    to={`/app/billing/quotes/${quote.quote_id}`}
                                                    className="inline-flex min-h-9 items-center rounded-lg bg-atlas-accent px-3 py-1.5 text-xs font-semibold text-white hover:opacity-90"
                                                >
                                                    {quote.status === 'Draft' ? 'Vérifier et envoyer' : 'Voir le devis'}
                                                </Link>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    </>
                )}
            </div>
        </RequireAuth>
    );
}
