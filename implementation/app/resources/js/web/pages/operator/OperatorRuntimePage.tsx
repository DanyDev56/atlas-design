import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { ApiClientError } from '@/api/client';
import { fetchOperatorAlerts, fetchOperatorHttpMetrics, fetchOperatorMaintenance, fetchOperatorRuntime, type OperatorAlertItem, type OperatorHttpMetricPage, type OperatorMaintenanceItem, type OperatorPage, type OperatorRuntimeSnapshot } from '@/api/operator';
import { OperatorFrame } from '@/components/operator/OperatorFrame';
import { useOperatorAuth } from '@/hooks/useOperatorAuth';

const roleLabels = { api: 'API', worker: 'Worker Outbox', scheduler: 'Scheduler' };
const stateLabels = { Current: 'À jour', Stale: 'Périmé', NotCollected: 'Sans signal' };
const alertLabels: Record<string, string> = {
    http_high_error_rate: 'Taux d’erreurs HTTP élevé',
    runtime_api_stale: 'Signal API périmé',
    runtime_worker_stale: 'Signal worker périmé',
    runtime_scheduler_stale: 'Signal scheduler périmé',
    subscription_webhook_failures: 'Webhooks abonnement en échec',
    backup_failed_or_stale: 'Sauvegarde en échec ou périmée',
};

function formatDate(value: string | null): string {
    return value ? new Intl.DateTimeFormat('fr-FR', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value)) : 'Jamais observé';
}

function formatSize(value: number | null): string {
    if (value === null) return 'Taille non collectée';
    return `${new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 1 }).format(value / 1_048_576)} Mo`;
}

export function OperatorRuntimePage() {
    const { session } = useOperatorAuth();
    const [runtime, setRuntime] = useState<OperatorRuntimeSnapshot | null>(null);
    const [maintenance, setMaintenance] = useState<OperatorPage<OperatorMaintenanceItem> | null>(null);
    const [httpMetrics, setHttpMetrics] = useState<OperatorHttpMetricPage | null>(null);
    const [alerts, setAlerts] = useState<OperatorPage<OperatorAlertItem> | null>(null);
    const [httpWindow, setHttpWindow] = useState(15);
    const [page, setPage] = useState(1);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const load = useCallback(async () => {
        if (!session?.token) return;
        setLoading(true);
        setError(null);
        try {
            const [nextRuntime, nextMaintenance, nextHttpMetrics, nextAlerts] = await Promise.all([
                fetchOperatorRuntime(session.token),
                fetchOperatorMaintenance(session.token, page),
                fetchOperatorHttpMetrics(session.token, httpWindow),
                fetchOperatorAlerts(session.token),
            ]);
            setRuntime(nextRuntime);
            setMaintenance(nextMaintenance);
            setHttpMetrics(nextHttpMetrics);
            setAlerts(nextAlerts);
        } catch (caught) {
            setError(caught instanceof ApiClientError ? caught.message : 'Les signaux d’exploitation n’ont pas pu être chargés.');
        } finally {
            setLoading(false);
        }
    }, [httpWindow, page, session?.token]);

    useEffect(() => {
        const previousTitle = document.title;
        document.title = 'Exploitation — Atlas';
        void load();
        return () => { document.title = previousTitle; };
    }, [load]);

    return (
        <OperatorFrame>
            <main className="mx-auto max-w-[90rem] px-5 py-10 sm:px-8 lg:px-10 lg:py-14">
                <Link to="/backoffice" className="text-sm font-semibold text-atlas-accent">← Retour à la vue d’ensemble</Link>
                <div className="mt-8"><p className="atlas-kicker">Exploitation</p><h1 className="mt-3 text-4xl font-semibold tracking-[-0.045em]">Runtime et continuité</h1><p className="mt-4 max-w-3xl leading-7 text-atlas-ink-muted">Heartbeats persistants, sonde PostgreSQL courante et résultats des sauvegardes/restaurations. Une absence de signal reste distincte d’un succès.</p></div>
                {error && <div role="alert" className="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error} <button type="button" className="font-semibold underline" onClick={() => void load()}>Réessayer</button></div>}

                <section className="mt-10"><div className="flex items-end justify-between"><div><h2 className="text-xl font-semibold">Rôles applicatifs</h2><p className="mt-1 text-sm text-atlas-ink-muted">Seuil de péremption : 2 minutes.</p></div>{loading && <span className="text-xs font-semibold text-atlas-accent">Actualisation…</span>}</div>
                    {runtime && <div className="mt-5 grid gap-4 md:grid-cols-3">{Object.entries(runtime.roles).map(([role, signal]) => <article key={role} className="rounded-2xl border border-atlas-border bg-white p-5 shadow-sm"><div className="flex items-start justify-between gap-3"><h3 className="font-semibold">{roleLabels[role as keyof typeof roleLabels]}</h3><span className={`rounded-full border px-2.5 py-1 text-xs font-semibold ${signal.status === 'Current' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : signal.status === 'Stale' ? 'border-red-200 bg-red-50 text-red-700' : 'border-amber-200 bg-amber-50 text-amber-700'}`}>{stateLabels[signal.status]}</span></div><p className="mt-5 text-sm text-atlas-ink-muted">Dernier signal : {formatDate(signal.recorded_at)}</p></article>)}</div>}
                    {runtime && <div className={`mt-4 rounded-xl border px-4 py-3 text-sm ${runtime.database_available ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-700'}`}>PostgreSQL : {runtime.database_available ? 'sonde courante réussie' : 'indisponible'}</div>}
                </section>

                <section className="mt-10"><div className="flex flex-wrap items-end justify-between gap-4"><div><h2 className="text-xl font-semibold">Trafic HTTP</h2><p className="mt-1 text-sm text-atlas-ink-muted">RED agrégé par gabarit de route : volume, erreurs 5xx et durée.</p></div><label className="text-sm font-semibold text-atlas-ink-muted">Fenêtre <select value={httpWindow} onChange={(event) => setHttpWindow(Number(event.target.value))} className="ml-2 min-h-10 rounded-xl border border-atlas-border bg-white px-3 text-atlas-ink"><option value={5}>5 min</option><option value={15}>15 min</option><option value={60}>1 h</option><option value={1440}>24 h</option></select></label></div>
                    <div className="mt-5 overflow-hidden rounded-2xl border border-atlas-border bg-white shadow-sm">
                        {!loading && httpMetrics?.items.length === 0 && <div className="p-10 text-center text-sm text-atlas-ink-muted">Aucune requête observée dans cette fenêtre.</div>}
                        {httpMetrics && <div className="divide-y divide-atlas-border">{httpMetrics.items.map((metric) => <article key={`${metric.method}-${metric.route_template}`} className="grid gap-3 p-5 lg:grid-cols-[minmax(0,2fr)_repeat(4,minmax(0,.7fr))] lg:items-center"><div className="min-w-0"><span className="mr-2 rounded-md bg-atlas-surface px-2 py-1 font-mono text-xs font-semibold">{metric.method}</span><span className="break-all font-mono text-sm">{metric.route_template}</span></div><p className="text-sm"><span className="block text-xs text-atlas-ink-muted">Requêtes</span>{metric.request_count}</p><p className="text-sm"><span className="block text-xs text-atlas-ink-muted">Erreurs 5xx</span>{metric.error_count}</p><p className="text-sm"><span className="block text-xs text-atlas-ink-muted">Taux 5xx</span>{metric.error_rate_percent?.toLocaleString('fr-FR') ?? '—'} %</p><p className="text-sm"><span className="block text-xs text-atlas-ink-muted">Moy. / max.</span>{metric.average_duration_ms?.toLocaleString('fr-FR') ?? '—'} / {metric.maximum_duration_ms.toLocaleString('fr-FR')} ms</p></article>)}</div>}
                    </div>
                </section>

                <section className="mt-10"><h2 className="text-xl font-semibold">Alertes opérationnelles</h2><p className="mt-1 text-sm text-atlas-ink-muted">États persistés et notifications externes anti-rafale. « Non notifiée » signifie qu’aucun webhook n’est configuré ou que sa remise a échoué.</p>
                    <div className="mt-5 overflow-hidden rounded-2xl border border-atlas-border bg-white shadow-sm">
                        {!loading && alerts?.items.length === 0 && <div className="p-10 text-center text-sm text-atlas-ink-muted">L’évaluateur n’a pas encore produit d’état.</div>}
                        {alerts && <div className="divide-y divide-atlas-border">{alerts.items.map((alert) => <article key={alert.key} className="grid gap-3 p-5 sm:grid-cols-[minmax(0,1.5fr)_.6fr_1fr_1fr] sm:items-center"><div><p className="font-semibold">{alertLabels[alert.key] ?? alert.key}</p><p className="mt-1 font-mono text-xs text-atlas-ink-muted">{alert.key}</p></div><span className={`w-fit rounded-full border px-2.5 py-1 text-xs font-semibold ${alert.state === 'Firing' ? 'border-red-200 bg-red-50 text-red-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700'}`}>{alert.state === 'Firing' ? 'En cours' : 'Saine'}</span><p className="text-sm text-atlas-ink-muted">Évaluée<br />{formatDate(alert.last_evaluated_at)}</p><p className="text-sm text-atlas-ink-muted">Notifiée<br />{formatDate(alert.last_notified_at)}</p></article>)}</div>}
                    </div>
                </section>

                <section className="mt-10"><h2 className="text-xl font-semibold">Sauvegardes et canarys</h2><p className="mt-1 text-sm text-atlas-ink-muted">Résultats enregistrés par les scripts officiels, sans chemin de fichier ni erreur brute.</p>
                    <div className="mt-5 overflow-hidden rounded-2xl border border-atlas-border bg-white shadow-sm">
                        {!loading && maintenance?.items.length === 0 && <div className="p-10 text-center text-sm text-atlas-ink-muted">Aucune exécution instrumentée. Lancez une sauvegarde ou une restauration canary.</div>}
                        {maintenance && <div className="divide-y divide-atlas-border">{maintenance.items.map((run) => <article key={`${run.kind}-${run.reference}`} className="grid gap-3 p-5 sm:grid-cols-[1fr_.7fr_1fr_1fr] sm:items-center"><div><p className="font-semibold">{run.kind === 'Backup' ? 'Sauvegarde' : 'Restauration canary'}</p><p className="mt-1 font-mono text-xs text-atlas-ink-muted">{run.reference}</p></div><span className={`w-fit rounded-full border px-2.5 py-1 text-xs font-semibold ${run.status === 'Succeeded' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-red-200 bg-red-50 text-red-700'}`}>{run.status === 'Succeeded' ? 'Réussie' : 'Échec'}</span><p className="text-sm text-atlas-ink-muted">{formatSize(run.size_bytes)}</p><p className="text-sm text-atlas-ink-muted">{formatDate(run.completed_at)}</p></article>)}</div>}
                        {maintenance && maintenance.total_pages > 1 && <div className="flex items-center justify-between border-t border-atlas-border px-5 py-4"><button type="button" disabled={page <= 1 || loading} onClick={() => setPage((value) => value - 1)} className="min-h-10 rounded-xl border border-atlas-border px-3 text-sm font-semibold disabled:opacity-40">Précédent</button><span className="text-sm text-atlas-ink-muted">Page {maintenance.page} sur {maintenance.total_pages}</span><button type="button" disabled={page >= maintenance.total_pages || loading} onClick={() => setPage((value) => value + 1)} className="min-h-10 rounded-xl border border-atlas-border px-3 text-sm font-semibold disabled:opacity-40">Suivant</button></div>}
                    </div>
                </section>
            </main>
        </OperatorFrame>
    );
}
