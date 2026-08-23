import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { ApiClientError } from '@/api/client';
import { fetchBetaParticipantDiagnostic, type BetaParticipantDiagnostic } from '@/api/operator';
import { OperatorFrame } from '@/components/operator/OperatorFrame';
import { useOperatorAuth } from '@/hooks/useOperatorAuth';

const stageLabels: Record<string, string> = {
    E0: 'Invité', E1: 'Vérifié', E2: 'Espace prêt', E3: 'Données prêtes',
    E4: 'Première valeur', E5: 'Réutilisé', E6: 'Décision',
};

function formatDate(value: string | null): string {
    return value ? new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : 'Non atteint';
}

export function OperatorBetaParticipantPage() {
    const { betaCode = '' } = useParams();
    const { session } = useOperatorAuth();
    const [result, setResult] = useState<BetaParticipantDiagnostic | null>(null);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (!session?.token) return;
        let cancelled = false;
        void fetchBetaParticipantDiagnostic(session.token, betaCode)
            .then((value) => { if (!cancelled) setResult(value); })
            .catch((caught) => { if (!cancelled) setError(caught instanceof ApiClientError ? caught.message : 'Le diagnostic n’a pas pu être chargé.'); });
        return () => { cancelled = true; };
    }, [betaCode, session?.token]);

    return (
        <OperatorFrame>
            <main className="mx-auto max-w-6xl px-5 py-10 sm:px-8 lg:py-14">
                <Link to="/backoffice/beta" className="text-sm font-semibold text-atlas-accent">← Retour à la cohorte</Link>
                {error && <div role="alert" className="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>}
                {!result && !error && <div className="mt-8 rounded-2xl border border-atlas-border bg-white p-8 text-sm text-atlas-ink-muted">Chargement du diagnostic pseudonymisé…</div>}
                {result && (
                    <>
                        <header className="mt-8 rounded-[1.5rem] bg-atlas-sidebar p-6 text-white shadow-lg sm:p-8">
                            <p className="text-xs font-semibold uppercase tracking-[.16em] text-white/50">Participant pseudonymisé</p>
                            <div className="mt-3 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                                <div><h1 className="text-4xl font-semibold tracking-[-0.04em]">{result.participant.beta_code}</h1><p className="mt-2 text-white/60">{result.participant.segment} · {result.participant.channel} · {result.participant.packaging_version}</p></div>
                                <div className="flex gap-2"><span className="rounded-full bg-white/10 px-3 py-1.5 text-xs font-semibold">{result.participant.pricing_cell}</span><span className="rounded-full bg-white/10 px-3 py-1.5 text-xs font-semibold">{result.participant.status}</span></div>
                            </div>
                        </header>

                        <section className="mt-8 rounded-2xl border border-atlas-border bg-white p-5 shadow-sm sm:p-6">
                            <h2 className="text-xl font-semibold">Progression observable</h2>
                            <div className="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                {Object.entries(result.participant.stage_dates).map(([stage, date]) => <div key={stage} className={`rounded-xl p-4 ${date ? 'bg-emerald-50' : 'bg-atlas-surface'}`}><p className="text-xs font-semibold uppercase tracking-[.1em] text-atlas-accent">{stage}</p><p className="mt-2 text-sm font-semibold">{stageLabels[stage]}</p><p className="mt-1 text-xs text-atlas-ink-muted">{formatDate(date)}</p></div>)}
                            </div>
                            <div className="mt-6 grid gap-4 border-t border-atlas-border pt-5 sm:grid-cols-3">
                                <div><p className="text-xs text-atlas-ink-muted">Chemin première valeur</p><p className="mt-1 font-semibold">{result.participant.first_value_path ?? 'Non atteint'}</p></div>
                                <div><p className="text-xs text-atlas-ink-muted">Dernier jalon</p><p className="mt-1 font-semibold">{result.participant.latest_review?.milestone ?? 'Non renseigné'}</p></div>
                                <div><p className="text-xs text-atlas-ink-muted">Support cumulé</p><p className="mt-1 font-semibold">{result.participant.latest_review ? `${result.participant.latest_review.support_minutes} min` : 'Non renseigné'}</p></div>
                            </div>
                        </section>

                        <section className="mt-6 grid gap-5 lg:grid-cols-2">
                            <article className="rounded-2xl border border-atlas-border bg-white p-5 shadow-sm sm:p-6"><h2 className="text-lg font-semibold">Analytics</h2><p className="mt-4 text-3xl font-semibold">{result.diagnostic.analytics.status}</p><dl className="mt-5 grid grid-cols-2 gap-4 text-sm"><div><dt className="text-atlas-ink-muted">Fraîcheur</dt><dd className="mt-1 font-semibold">{result.diagnostic.analytics.freshness_status ?? '—'}</dd></div><div><dt className="text-atlas-ink-muted">Complétude</dt><dd className="mt-1 font-semibold">{result.diagnostic.analytics.completeness_status ?? '—'}</dd></div></dl><p className="mt-5 text-xs text-atlas-ink-muted">Publié : {formatDate(result.diagnostic.analytics.published_at)}</p></article>
                            <article className="rounded-2xl border border-atlas-border bg-white p-5 shadow-sm sm:p-6"><h2 className="text-lg font-semibold">Santé de l’activité</h2><p className="mt-4 text-3xl font-semibold">{result.diagnostic.business_health.assessment_status ?? 'NoData'}</p><dl className="mt-5 grid grid-cols-2 gap-4 text-sm"><div><dt className="text-atlas-ink-muted">Fiabilité</dt><dd className="mt-1 font-semibold">{result.diagnostic.business_health.reliability ?? '—'}</dd></div><div><dt className="text-atlas-ink-muted">Couverture</dt><dd className="mt-1 font-semibold">{result.diagnostic.business_health.coverage_percent === null ? '—' : `${result.diagnostic.business_health.coverage_percent} %`}</dd></div></dl><p className="mt-5 text-xs text-atlas-ink-muted">Évalué : {formatDate(result.diagnostic.business_health.assessed_at)}</p></article>
                        </section>

                        <p className="mt-6 text-xs text-atlas-ink-muted">Source : {result.diagnostic.source}. Aucun nom, email, montant ou contenu métier n’est exposé.</p>
                    </>
                )}
            </main>
        </OperatorFrame>
    );
}
