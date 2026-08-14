import { FormEvent, useEffect, useMemo, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { createOpportunity, getClient, listOpportunities } from '@/api/crm';
import { StatusBadge } from '@/components/crm/StatusBadge';
import { RequireAuth } from '@/components/layout/RequireAuth';
import { EmptyState } from '@/components/ui/EmptyState';
import { PageSkeleton } from '@/components/ui/PageSkeleton';
import { ErrorBanner, FormField, SubmitButton, inputClassName } from '@/components/auth/AuthLayout';
import { useAuth } from '@/hooks/useAuth';
import type { ClientDetail, OpportunitySummary } from '@/types/api';
import { formatMoney } from '@/utils/format';

export function ClientDetailPage() {
    const { clientId } = useParams<{ clientId: string }>();
    const { session } = useAuth();
    const token = session?.token ?? null;
    const workspaceId = session?.workspaceId ?? null;

    const [client, setClient] = useState<ClientDetail | null>(null);
    const [opportunities, setOpportunities] = useState<OpportunitySummary[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [showForm, setShowForm] = useState(false);
    const [title, setTitle] = useState('');
    const [amount, setAmount] = useState('');
    const [creating, setCreating] = useState(false);

    useEffect(() => {
        if (!token || !workspaceId || !clientId) {
            setLoading(false);
            return;
        }

        let cancelled = false;
        const activeToken = token;
        const activeWorkspaceId = workspaceId;
        const selectedClientId = clientId;

        async function load() {
            setLoading(true);
            setError(null);
            try {
                const [clientData, allOpportunities] = await Promise.all([
                    getClient(activeToken, activeWorkspaceId, selectedClientId),
                    listOpportunities(activeToken, activeWorkspaceId),
                ]);
                if (!cancelled) {
                    setClient(clientData);
                    setOpportunities(allOpportunities.filter((o) => o.client_id === selectedClientId));
                }
            } catch (err) {
                if (!cancelled) {
                    setError(err instanceof Error ? err.message : 'Chargement impossible');
                }
            } finally {
                if (!cancelled) setLoading(false);
            }
        }

        void load();

        return () => {
            cancelled = true;
        };
    }, [token, workspaceId, clientId]);

    const sortedOpportunities = useMemo(
        () => [...opportunities].sort((a, b) => a.title.localeCompare(b.title, 'fr')),
        [opportunities],
    );

    async function onCreateOpportunity(event: FormEvent) {
        event.preventDefault();
        if (!token || !workspaceId || !clientId) return;

        setCreating(true);
        setError(null);
        try {
            const cents = amount ? Math.round(parseFloat(amount.replace(',', '.')) * 100) : undefined;
            await createOpportunity(token, workspaceId, {
                client_id: clientId,
                title,
                estimated_amount_cents: cents,
                currency: 'EUR',
            });
            setTitle('');
            setAmount('');
            setShowForm(false);
            const all = await listOpportunities(token, workspaceId);
            setOpportunities(all.filter((o) => o.client_id === clientId));
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Création impossible');
        } finally {
            setCreating(false);
        }
    }

    if (!clientId) {
        return (
            <RequireAuth>
                <p className="text-sm text-red-800">Client introuvable.</p>
            </RequireAuth>
        );
    }

    return (
        <RequireAuth>
            <div className="mx-auto max-w-4xl">
                <Link to="/app/crm" className="text-sm font-medium text-atlas-accent hover:underline">
                    ← Retour aux clients
                </Link>

                {loading && (
                    <div className="mt-8">
                        <PageSkeleton rows={2} />
                    </div>
                )}

                {error && (
                    <div className="mt-6">
                        <ErrorBanner message={error} />
                    </div>
                )}

                {client && !loading && (
                    <>
                        <div className="mt-6 flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h2 className="text-3xl font-semibold tracking-tight text-atlas-ink">
                                    {client.display_name}
                                </h2>
                                <p className="mt-2 text-sm text-atlas-ink-muted">
                                    {client.kind === 'Organization' ? 'Organisation' : 'Particulier'}
                                </p>
                            </div>
                            <StatusBadge status={client.status} />
                        </div>

                        <section className="mt-10">
                            <div className="mb-4 flex items-center justify-between gap-4">
                                <h3 className="text-lg font-semibold text-atlas-ink">Opportunités</h3>
                                {!showForm && (
                                    <button
                                        type="button"
                                        onClick={() => setShowForm(true)}
                                        className="rounded-xl bg-atlas-accent px-4 py-2 text-sm font-semibold text-white hover:opacity-90"
                                    >
                                        Nouvelle opportunité
                                    </button>
                                )}
                            </div>

                            {showForm && (
                                <form
                                    onSubmit={onCreateOpportunity}
                                    className="mb-6 rounded-2xl border border-atlas-border bg-atlas-card p-6 shadow-sm"
                                >
                                    <FormField label="Titre">
                                        <input
                                            required
                                            className={inputClassName}
                                            value={title}
                                            onChange={(e) => setTitle(e.target.value)}
                                            placeholder="Refonte site web"
                                        />
                                    </FormField>
                                    <div className="mt-4">
                                        <FormField label="Montant estimé (€, optionnel)">
                                            <input
                                                type="text"
                                                inputMode="decimal"
                                                className={inputClassName}
                                                value={amount}
                                                onChange={(e) => setAmount(e.target.value)}
                                                placeholder="1200"
                                            />
                                        </FormField>
                                    </div>
                                    <div className="mt-4 flex gap-3">
                                        <SubmitButton loading={creating}>Créer</SubmitButton>
                                        <button
                                            type="button"
                                            onClick={() => setShowForm(false)}
                                            className="rounded-xl border border-atlas-border px-4 py-3 text-sm font-medium text-atlas-ink-muted"
                                        >
                                            Annuler
                                        </button>
                                    </div>
                                </form>
                            )}

                            {sortedOpportunities.length === 0 && (
                                <EmptyState
                                    title="Aucune opportunité"
                                    description="Créez une opportunité pour préparer un devis."
                                />
                            )}

                            {sortedOpportunities.length > 0 && (
                                <ul className="divide-y divide-atlas-border overflow-hidden rounded-2xl border border-atlas-border bg-atlas-card shadow-sm">
                                    {sortedOpportunities.map((opp) => (
                                        <li key={opp.opportunity_id}>
                                            <Link
                                                to={`/app/crm/opportunities/${opp.opportunity_id}`}
                                                className="flex items-center justify-between gap-4 px-6 py-4 hover:bg-atlas-surface"
                                            >
                                                <div>
                                                    <p className="font-medium text-atlas-ink">{opp.title}</p>
                                                    {opp.estimated_amount_cents != null && (
                                                        <p className="mt-0.5 text-xs text-atlas-ink-muted">
                                                            {formatMoney(opp.estimated_amount_cents, opp.currency)}
                                                        </p>
                                                    )}
                                                </div>
                                                <StatusBadge status={opp.status} />
                                            </Link>
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
