import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { ApiClientError } from '@/api/client';
import {
    fetchOperatorSubscriptions,
    fetchOperatorSubscriptionWebhooks,
    previewOperatorSubscriptionReconciliation,
    reconcileOperatorSubscription,
    type OperatorPage,
    type OperatorSubscriptionItem,
    type OperatorSubscriptionWebhookItem,
    type OperatorSubscriptionReconciliationPreview,
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
    const [target, setTarget] = useState<OperatorSubscriptionItem | null>(null);
    const [preview, setPreview] = useState<OperatorSubscriptionReconciliationPreview | null>(null);
    const [actionBusy, setActionBusy] = useState(false);
    const [actionError, setActionError] = useState<string | null>(null);
    const [actionMessage, setActionMessage] = useState<string | null>(null);
    const [idempotencyKey, setIdempotencyKey] = useState<string | null>(null);
    const reasonCode = 'subscription.provider-drift-reviewed';
    const hasReconcilePermission = session?.permissions.includes('operations.subscriptions.reconcile') ?? false;
    const actionsEnabled = Boolean(session?.actionsEnabled && !session.readOnly);
    const stepUpActive = Boolean(session?.stepUpExpiresAt && Date.parse(session.stepUpExpiresAt) > Date.now());
    const canReconcile = hasReconcilePermission && actionsEnabled && stepUpActive;

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

    const prepareReconciliation = async (item: OperatorSubscriptionItem) => {
        if (!session?.token || !canReconcile) return;
        setTarget(item); setPreview(null); setActionError(null); setActionMessage(null); setIdempotencyKey(null); setActionBusy(true);
        try {
            const next = await previewOperatorSubscriptionReconciliation(session.token, item.reference, item.version, reasonCode);
            setPreview(next);
            setIdempotencyKey(crypto.randomUUID());
        } catch (caught) {
            setActionError(caught instanceof ApiClientError ? caught.message : 'La comparaison avec Stripe a échoué.');
        } finally { setActionBusy(false); }
    };

    const confirmReconciliation = async () => {
        if (!session?.token || !target || !preview || preview.aligned) return;
        const key = idempotencyKey ?? crypto.randomUUID();
        setIdempotencyKey(key); setActionBusy(true); setActionError(null);
        try {
            const outcome = await reconcileOperatorSubscription(session.token, target.reference, target.version, reasonCode, preview.preview_fingerprint, key);
            setActionMessage(outcome.replayed ? 'La confirmation précédente a été retrouvée.' : 'L’abonnement Atlas a été réaligné avec Stripe.');
            setTarget(null); setPreview(null); setIdempotencyKey(null); await load();
        } catch (caught) {
            setActionError(caught instanceof ApiClientError ? caught.message : 'La confirmation est incertaine. Réessayez sans modifier la proposition.');
        } finally { setActionBusy(false); }
    };

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

                {actionMessage && <div role="status" className="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{actionMessage}</div>}
                {hasReconcilePermission && !actionsEnabled && <div className="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">Les réconciliations sont verrouillées par la configuration du back-office.</div>}
                {hasReconcilePermission && actionsEnabled && !stepUpActive && <div className="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">Renouvelez l’authentification renforcée depuis l’en-tête pour comparer et corriger un abonnement.</div>}

                {target && (
                    <section className="mt-6 rounded-2xl border border-amber-200 bg-amber-50/70 p-5 shadow-sm" aria-labelledby="subscription-reconciliation-title">
                        <div className="flex items-start justify-between gap-4"><div><p className="atlas-kicker">Action sensible bornée</p><h2 id="subscription-reconciliation-title" className="mt-2 text-xl font-semibold">Comparer Atlas à Stripe</h2><p className="mt-2 text-sm text-atlas-ink-muted">{target.reference} · {target.billing_environment}</p></div><button type="button" className="text-sm font-semibold underline" onClick={() => { setTarget(null); setPreview(null); setActionError(null); setIdempotencyKey(null); }}>Annuler</button></div>
                        {actionBusy && !preview && <p className="mt-5 text-sm font-semibold text-atlas-accent">Lecture sécurisée chez Stripe…</p>}
                        {preview && <div className="mt-5 rounded-xl border border-atlas-border bg-white p-4">
                            <p className="font-semibold">{preview.aligned ? 'Aucun écart détecté' : `${preview.changes.length} écart${preview.changes.length > 1 ? 's' : ''} détecté${preview.changes.length > 1 ? 's' : ''}`}</p>
                            <p className="mt-1 text-xs text-atlas-ink-muted">État Stripe observé le {formatDate(preview.provider_observed_at)}</p>
                            {preview.changes.length > 0 && <dl className="mt-4 divide-y divide-atlas-border rounded-xl border border-atlas-border">{preview.changes.map((change) => <div key={change.field} className="grid gap-2 p-3 text-sm sm:grid-cols-[1fr_1fr_1fr]"><dt className="font-semibold">{change.field}</dt><dd className="text-atlas-ink-muted">Atlas : {String(change.current ?? '—')}</dd><dd>Stripe : {String(change.provider ?? '—')}</dd></div>)}</dl>}
                            {!preview.aligned && <><p className="mt-4 text-sm text-amber-800">Seul Atlas sera modifié ; Stripe reste la source observée. Les droits de l’espace seront recalculés.</p><button type="button" disabled={actionBusy} onClick={() => void confirmReconciliation()} className="mt-4 min-h-11 rounded-xl bg-atlas-ink px-4 font-semibold text-white disabled:opacity-50">{actionBusy ? 'Confirmation…' : 'Confirmer le réalignement'}</button></>}
                        </div>}
                        {actionError && <div role="alert" className="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{actionError}</div>}
                    </section>
                )}

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
                        <article key={item.reference} className="grid gap-4 p-5 md:grid-cols-[1.3fr_.8fr_.8fr_1fr_auto] md:items-center">
                            <div><p className="font-semibold">{item.reference}</p><p className="mt-1 text-xs text-atlas-ink-muted">{'workspace_reference' in item ? item.workspace_reference : item.event_type}</p></div>
                            <div><span className={`inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold ${environmentClass(item.billing_environment)}`}>{item.billing_environment === 'Unknown' ? 'Indéterminé' : item.billing_environment}</span><p className="mt-1 text-xs text-atlas-ink-muted">{item.provider}</p></div>
                            <div><span className={`inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold ${badgeClass(item.status)}`}>{statusLabels[item.status]}</span>{'attempts' in item && <p className="mt-1 text-xs text-atlas-ink-muted">{item.attempts} tentative(s)</p>}</div>
                            <div className="text-sm text-atlas-ink-muted">{'workspace_reference' in item ? <><p>{item.plan_code} · {item.billing_interval}</p><p className="mt-1">Échéance {formatDate(item.current_period_end)}</p></> : <><p>Reçu {formatDate(item.received_at)}</p><p className="mt-1">Traité {formatDate(item.processed_at)}</p></>}</div>
                            <div>{'workspace_reference' in item && <button type="button" disabled={!canReconcile || actionBusy} onClick={() => void prepareReconciliation(item)} className="min-h-10 whitespace-nowrap rounded-xl border border-atlas-border px-3 text-sm font-semibold disabled:cursor-not-allowed disabled:opacity-40">Comparer</button>}</div>
                        </article>
                    ))}</div>}
                    {result && result.total_pages > 1 && <div className="flex items-center justify-between border-t border-atlas-border px-5 py-4"><button type="button" disabled={page <= 1 || loading} onClick={() => setPage((value) => value - 1)} className="min-h-10 rounded-xl border border-atlas-border px-3 text-sm font-semibold disabled:opacity-40">Précédent</button><span className="text-sm text-atlas-ink-muted">Page {result.page} sur {result.total_pages}</span><button type="button" disabled={page >= result.total_pages || loading} onClick={() => setPage((value) => value + 1)} className="min-h-10 rounded-xl border border-atlas-border px-3 text-sm font-semibold disabled:opacity-40">Suivant</button></div>}
                </section>
            </main>
        </OperatorFrame>
    );
}
