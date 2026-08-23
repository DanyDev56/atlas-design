import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { ApiClientError } from '@/api/client';
import {
    fetchBetaCohortOverview,
    fetchBetaParticipants,
    type BetaCohortOverview,
    type BetaParticipant,
    type OperatorPage,
} from '@/api/operator';
import { OperatorFrame } from '@/components/operator/OperatorFrame';
import { Icon } from '@/components/ui/Icon';
import { useOperatorAuth } from '@/hooks/useOperatorAuth';

const stages = ['All', 'E0', 'E1', 'E2', 'E3', 'E4', 'E5', 'E6'];
const stageLabels: Record<string, string> = {
    All: 'Toutes les étapes', E0: 'Invité', E1: 'Vérifié', E2: 'Espace prêt',
    E3: 'Données prêtes', E4: 'Première valeur', E5: 'Réutilisé', E6: 'Décision',
};

function formatDate(value: string | null): string {
    return value ? new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium' }).format(new Date(value)) : '—';
}

export function OperatorBetaCohortPage() {
    const { session } = useOperatorAuth();
    const canReadMetrics = session?.permissions.includes('operations.metrics.read-product') ?? false;
    const [overview, setOverview] = useState<BetaCohortOverview | null>(null);
    const [participants, setParticipants] = useState<OperatorPage<BetaParticipant> | null>(null);
    const [stage, setStage] = useState('All');
    const [cell, setCell] = useState('All');
    const [status, setStatus] = useState('All');
    const [page, setPage] = useState(1);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const load = useCallback(async () => {
        if (!session?.token) return;
        setLoading(true);
        setError(null);
        try {
            const [list, metrics] = await Promise.all([
                fetchBetaParticipants(session.token, { stage, cell, status, page }),
                canReadMetrics ? fetchBetaCohortOverview(session.token) : Promise.resolve(null),
            ]);
            setParticipants(list);
            setOverview(metrics);
        } catch (caught) {
            setError(caught instanceof ApiClientError ? caught.message : 'La cohorte n’a pas pu être chargée.');
        } finally {
            setLoading(false);
        }
    }, [canReadMetrics, cell, page, session?.token, stage, status]);

    useEffect(() => {
        const previousTitle = document.title;
        document.title = 'Cohorte beta — Atlas';
        void load();
        return () => { document.title = previousTitle; };
    }, [load]);

    function updateFilter(setter: (value: string) => void, value: string) {
        setter(value);
        setPage(1);
    }

    return (
        <OperatorFrame>
            <main className="mx-auto max-w-[90rem] px-5 py-10 sm:px-8 lg:px-10 lg:py-14">
                <Link to="/backoffice" className="text-sm font-semibold text-atlas-accent">← Retour à la vue d’ensemble</Link>
                <div className="mt-8">
                    <p className="atlas-kicker">Produit et recherche</p>
                    <h1 className="mt-3 text-4xl font-semibold tracking-[-0.045em] sm:text-5xl">Cohorte beta</h1>
                    <p className="mt-4 max-w-3xl text-base leading-7 text-atlas-ink-muted">Activation E0–E6, blocages et décisions pricing. Seuls des pseudonymes et des codes structurés sont affichés.</p>
                </div>

                {error && <div role="alert" className="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error} <button type="button" onClick={() => void load()} className="ml-2 font-semibold underline">Réessayer</button></div>}

                {overview ? (
                    <>
                        <section className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            {[
                                ['Participants', overview.participant_count],
                                ['Actifs', overview.active_count],
                                ['Bloqués', overview.blocked_count],
                                ['Décisions', overview.decision_count],
                            ].map(([label, value]) => <article key={label} className="rounded-2xl border border-atlas-border bg-white p-5 shadow-sm"><p className="text-sm text-atlas-ink-muted">{label}</p><p className="mt-2 text-3xl font-semibold tabular-nums">{value}</p></article>)}
                        </section>
                        <section className="mt-8 rounded-2xl border border-atlas-border bg-white p-5 shadow-sm sm:p-6">
                            <div><h2 className="text-xl font-semibold">Entonnoir d’activation</h2><p className="mt-1 text-sm text-atlas-ink-muted">Chaque taux conserve son effectif et son dénominateur.</p></div>
                            <div className="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
                                {overview.funnel.map((step) => (
                                    <div key={step.stage} className="rounded-xl bg-atlas-surface p-4">
                                        <p className="text-xs font-semibold uppercase tracking-[.1em] text-atlas-accent">{step.stage}</p>
                                        <p className="mt-2 text-sm font-semibold">{stageLabels[step.stage]}</p>
                                        <p className="mt-3 text-2xl font-semibold tabular-nums">{step.reached}<span className="text-sm font-normal text-atlas-ink-muted"> / {step.denominator}</span></p>
                                        <p className="mt-1 text-xs text-atlas-ink-muted">{step.rate_percent === null ? 'Taux non calculable' : `${step.rate_percent} %`}</p>
                                        {step.median_duration_hours !== null && <p className="mt-2 text-xs text-atlas-ink-muted">Médiane {step.median_duration_hours} h</p>}
                                    </div>
                                ))}
                            </div>
                        </section>
                    </>
                ) : !canReadMetrics && (
                    <div className="mt-8 rounded-2xl border border-atlas-border bg-white p-5 text-sm text-atlas-ink-muted">Le grant <code>operations.metrics.read-product</code> est requis pour l’entonnoir agrégé.</div>
                )}

                <section className="mt-8">
                    <div className="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                        <div><h2 className="text-xl font-semibold">Participants pseudonymisés</h2><p className="mt-1 text-sm text-atlas-ink-muted">Aucun nom, email ou contenu métier n’est chargé.</p></div>
                        <div className="grid gap-3 sm:grid-cols-3">
                            <label className="text-sm font-medium">Étape<select value={stage} onChange={(event) => updateFilter(setStage, event.target.value)} className="mt-2 block min-h-11 w-full rounded-xl border border-atlas-border bg-white px-3">{stages.map((item) => <option key={item} value={item}>{stageLabels[item]}</option>)}</select></label>
                            <label className="text-sm font-medium">Cellule<select value={cell} onChange={(event) => updateFilter(setCell, event.target.value)} className="mt-2 block min-h-11 w-full rounded-xl border border-atlas-border bg-white px-3"><option value="All">Toutes</option><option>P19</option><option>P24</option><option>P29</option></select></label>
                            <label className="text-sm font-medium">Statut<select value={status} onChange={(event) => updateFilter(setStatus, event.target.value)} className="mt-2 block min-h-11 w-full rounded-xl border border-atlas-border bg-white px-3"><option value="All">Tous</option><option value="Active">Actif</option><option value="Exited">Sorti</option></select></label>
                        </div>
                    </div>

                    <div className="mt-5 overflow-hidden rounded-2xl border border-atlas-border bg-white shadow-sm">
                        <div className="border-b border-atlas-border px-5 py-4 text-sm text-atlas-ink-muted">{loading ? 'Actualisation…' : `${participants?.total ?? 0} participant(s)`}</div>
                        {!loading && participants?.items.length === 0 && <div className="p-10 text-center text-sm text-atlas-ink-muted">Aucun participant pour ces filtres.</div>}
                        <div className="divide-y divide-atlas-border">
                            {participants?.items.map((participant) => (
                                <article key={participant.beta_code} className="grid gap-5 p-5 md:grid-cols-[1.1fr_.8fr_.8fr_auto] md:items-center">
                                    <div><div className="flex items-center gap-2"><h3 className="font-semibold">{participant.beta_code}</h3>{participant.blocked && <span className="rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-700">À débloquer</span>}</div><p className="mt-1 text-xs text-atlas-ink-muted">{participant.segment} · {participant.channel}</p></div>
                                    <div><p className="text-xs text-atlas-ink-muted">Étape actuelle</p><p className="mt-1 font-semibold">{participant.current_stage} · {stageLabels[participant.current_stage]}</p><p className="mt-1 text-xs text-atlas-ink-muted">{formatDate(participant.current_stage_at)}</p></div>
                                    <div><p className="text-xs text-atlas-ink-muted">Pricing</p><p className="mt-1 font-semibold">{participant.pricing_cell}</p><p className="mt-1 text-xs text-atlas-ink-muted">{participant.pricing_decision?.decision ?? 'Décision en attente'}</p></div>
                                    <Link to={`/backoffice/beta/${participant.beta_code}`} className="inline-flex min-h-10 items-center justify-center gap-2 rounded-xl border border-atlas-border px-3.5 text-sm font-semibold">Diagnostic <Icon name="chevron-right" className="size-4" /></Link>
                                </article>
                            ))}
                        </div>
                        {participants && participants.total_pages > 1 && <div className="flex items-center justify-between border-t border-atlas-border px-5 py-4"><button type="button" disabled={page <= 1 || loading} onClick={() => setPage((value) => value - 1)} className="rounded-xl border border-atlas-border px-3 py-2 text-sm font-semibold disabled:opacity-40">Précédent</button><span className="text-sm text-atlas-ink-muted">{page} / {participants.total_pages}</span><button type="button" disabled={page >= participants.total_pages || loading} onClick={() => setPage((value) => value + 1)} className="rounded-xl border border-atlas-border px-3 py-2 text-sm font-semibold disabled:opacity-40">Suivant</button></div>}
                    </div>
                </section>
            </main>
        </OperatorFrame>
    );
}
