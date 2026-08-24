import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { ApiClientError } from '@/api/client';
import {
    fetchOperatorSubscriptions,
    fetchOperatorSubscriptionWebhooks,
    type OperatorPage,
    type OperatorSubscriptionItem,
    type OperatorSubscriptionWebhookItem,
} from '@/api/operator';
import { OperatorFrame } from '@/components/operator/OperatorFrame';
import { useOperatorAuth } from '@/hooks/useOperatorAuth';

type View = 'subscriptions' | 'webhooks';

const statusOptions = {
    subscriptions: ['All', 'Active', 'PastDue', 'Canceled'],
    webhooks: ['All', 'Received', 'Processing', 'Processed', 'Ignored', 'Deferred', 'Failed'],
};
const statusLabels: Record<string, string> = {
    All: 'Tous', Active: 'Actif', PastDue: 'Paiement en échec', Canceled: 'Résilié', Received: 'Reçu',
    Processing: 'Traitement', Processed: 'Traité', Ignored: 'Ignoré', Deferred: 'Différé', Failed: 'Échec',
};

function formatDate(value: string | null): string {
    return value ? new Intl.DateTimeFormat('fr-FR', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value)) : '—';
}

function badgeClass(status: string): string {
    if (['PastDue', 'Failed'].includes(status)) return 'border-red-200 bg-red-50 text-red-700';
    if (['Received', 'Processing', 'Deferred'].includes(status)) return 'border-amber-200 bg-amber-50 text-amber-700';
    if (['Canceled', 'Ignored'].includes(status)) return 'border-atlas-border bg-atlas-surface text-atlas-ink-muted';
    return 'border-emerald-200 bg-emerald-50 text-emerald-700';
}

function environmentClass(environment: string): string {
    if (environment === 'Live') return 'border-violet-200 bg-violet-50 text-violet-700';
    if (environment === 'Sandbox') return 'border-sky-200 bg-sky-50 text-sky-700';
    return 'border-amber-200 bg-amber-50 text-amber-700';
}

export function OperatorSubscriptionsPage() {
    const { session } = useOperatorAuth();
    const [view, setView] = useState<View>('subscriptions');
    const [status, setStatus] = useState('All');
    const [environment, setEnvironment] = useState('All');
    const [page, setPage] = useState(1);
    const [result, setResult] = useState<OperatorPage<OperatorSubscriptionItem | OperatorSubscriptionWebhookItem> | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const load = useCallback(async () => {
        if (!session?.token) return;
        setLoading(true);
        setError(null);
        try {
            const next = view === 'subscriptions'
                ? await fetchOperatorSubscriptions(session.token, status, environment, page)
                : await fetchOperatorSubscriptionWebhooks(session.token, status, environment, page);
            setResult(next);
        } catch (caught) {
            setError(caught instanceof ApiClientError ? caught.message : 'Le registre de facturation n’a pas pu être chargé.');
        } finally {
            setLoading(false);
        }
    }, [environment, page, session?.token, status, view]);

    useEffect(() => {
        const previousTitle = document.title;
        document.title = 'Abonnements et webhooks — Atlas';
        void load();
        return () => { document.title = previousTitle; };
    }, [load]);

    const switchView = (next: View) => { setView(next); setStatus('All'); setPage(1); setResult(null); };

    return (
        <OperatorFrame>
            <main className="mx-auto max-w-[90rem] px-5 py-10 sm:px-8 lg:px-10 lg:py-14">
                <Link to="/backoffice" className="text-sm font-semibold text-atlas-accent">← Retour à la vue d’ensemble</Link>
                <div className="mt-8">
                    <p className="atlas-kicker">Facturation récurrente</p>
                    <h1 className="mt-3 text-4xl font-semibold tracking-[-0.045em]">Abonnements et webhooks</h1>
                    <p className="mt-4 max-w-3xl leading-7 text-atlas-ink-muted">Suivi en lecture seule, sans UUID Workspace, référence Stripe, payload ni motif d’échec brut.</p>
                </div>

                <div className="mt-8 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900">
                    <p className="font-semibold">Les environnements ne sont jamais fusionnés silencieusement.</p>
                    <p className="mt-1 text-amber-800/80">Chaque ligne conserve l’environnement observé à son ingestion. « Indéterminé » désigne les données antérieures à cette instrumentation.</p>
                </div>

                <div className="mt-8 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div className="flex gap-2" role="tablist" aria-label="Registre">
                        {(['subscriptions', 'webhooks'] as View[]).map((item) => <button key={item} type="button" role="tab" aria-selected={view === item} onClick={() => switchView(item)} className={`min-h-11 rounded-xl px-4 text-sm font-semibold ${view === item ? 'bg-atlas-sidebar text-white' : 'border border-atlas-border bg-white'}`}>{item === 'subscriptions' ? 'Abonnements' : 'Webhooks'}</button>)}
                    </div>
                    <div className="flex flex-col gap-3 sm:flex-row">
                        <label className="text-sm font-medium">Environnement<select value={environment} onChange={(event) => { setEnvironment(event.target.value); setPage(1); }} className="mt-2 block min-h-11 min-w-44 rounded-xl border border-atlas-border bg-white px-3"><option value="All">Tous (séparés)</option><option value="Sandbox">Sandbox</option><option value="Live">Live</option><option value="Unknown">Indéterminé</option></select></label>
                        <label className="text-sm font-medium">État<select value={status} onChange={(event) => { setStatus(event.target.value); setPage(1); }} className="mt-2 block min-h-11 min-w-48 rounded-xl border border-atlas-border bg-white px-3">{statusOptions[view].map((item) => <option key={item} value={item}>{statusLabels[item]}</option>)}</select></label>
                    </div>
                </div>

                {error && <div role="alert" className="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error} <button type="button" className="font-semibold underline" onClick={() => void load()}>Réessayer</button></div>}
                <section className="mt-6 overflow-hidden rounded-2xl border border-atlas-border bg-white shadow-sm">
                    <div className="flex items-center justify-between border-b border-atlas-border px-5 py-4"><p className="text-sm text-atlas-ink-muted">{result ? `${result.total} résultat${result.total > 1 ? 's' : ''}` : 'Chargement…'}</p>{loading && <span className="text-xs font-semibold text-atlas-accent">Actualisation…</span>}</div>
                    {!loading && result?.items.length === 0 && <div className="p-10 text-center text-sm text-atlas-ink-muted">Aucune donnée pour ces filtres.</div>}
                    {result && result.items.length > 0 && <div className="divide-y divide-atlas-border">{result.items.map((item) => (
                        <article key={item.reference} className="grid gap-4 p-5 md:grid-cols-[1.3fr_.8fr_.8fr_1fr] md:items-center">
                            <div><p className="font-semibold">{item.reference}</p><p className="mt-1 text-xs text-atlas-ink-muted">{'workspace_reference' in item ? item.workspace_reference : item.event_type}</p></div>
                            <div><span className={`inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold ${environmentClass(item.billing_environment)}`}>{item.billing_environment === 'Unknown' ? 'Indéterminé' : item.billing_environment}</span><p className="mt-1 text-xs text-atlas-ink-muted">{item.provider}</p></div>
                            <div><span className={`inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold ${badgeClass(item.status)}`}>{statusLabels[item.status]}</span>{'attempts' in item && <p className="mt-1 text-xs text-atlas-ink-muted">{item.attempts} tentative(s)</p>}</div>
                            <div className="text-sm text-atlas-ink-muted">{'workspace_reference' in item ? <><p>{item.plan_code} · {item.billing_interval}</p><p className="mt-1">Échéance {formatDate(item.current_period_end)}</p></> : <><p>Reçu {formatDate(item.received_at)}</p><p className="mt-1">Traité {formatDate(item.processed_at)}</p></>}</div>
                        </article>
                    ))}</div>}
                    {result && result.total_pages > 1 && <div className="flex items-center justify-between border-t border-atlas-border px-5 py-4"><button type="button" disabled={page <= 1 || loading} onClick={() => setPage((value) => value - 1)} className="min-h-10 rounded-xl border border-atlas-border px-3 text-sm font-semibold disabled:opacity-40">Précédent</button><span className="text-sm text-atlas-ink-muted">Page {result.page} sur {result.total_pages}</span><button type="button" disabled={page >= result.total_pages || loading} onClick={() => setPage((value) => value + 1)} className="min-h-10 rounded-xl border border-atlas-border px-3 text-sm font-semibold disabled:opacity-40">Suivant</button></div>}
                </section>
            </main>
        </OperatorFrame>
    );
}
