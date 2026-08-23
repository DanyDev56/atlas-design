import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { completeAdvisorRecommendation, dismissAdvisorRecommendation, getAdvisorOverview } from '@/api/advisor';
import { ApiClientError } from '@/api/client';
import { ErrorBanner } from '@/components/auth/AuthLayout';
import { RequireAuth } from '@/components/layout/RequireAuth';
import { EmptyState } from '@/components/ui/EmptyState';
import { PageHeader } from '@/components/ui/PageHeader';
import { PageSkeleton } from '@/components/ui/PageSkeleton';
import { useAuth } from '@/hooks/useAuth';
import type { AdvisorDismissalReason, AdvisorOverview, AdvisorRecommendation } from '@/types/api';
import { getRecommendationAction, getRecommendationPresentation } from '@/utils/advisor';
import { formatConfidence, formatEffort, formatImpact, formatPriority, formatUrgency } from '@/utils/format';

function formatAdvisorDate(value: string): string {
    const date = new Date(value);

    if (Number.isNaN(date.getTime())) return 'Date indisponible';

    return new Intl.DateTimeFormat('fr-FR', {
        dateStyle: 'long',
        timeStyle: 'short',
    }).format(date);
}

function formatValidityDate(value: string): string {
    const date = new Date(value);

    if (Number.isNaN(date.getTime())) return 'échéance indisponible';

    return new Intl.DateTimeFormat('fr-FR', { dateStyle: 'long' }).format(date);
}

function isOverviewMissing(error: unknown): boolean {
    return error instanceof ApiClientError
        && (error.status === 404 || error.body.messages?.includes('Overview not found.'));
}

function priorityClasses(priority: string, primary: boolean): string {
    if (primary) return 'bg-amber-300 text-amber-950';
    if (priority === 'Critical') return 'bg-red-50 text-red-800 ring-red-200';
    if (priority === 'High') return 'bg-amber-50 text-amber-900 ring-amber-200';

    return 'bg-slate-50 text-atlas-ink-muted ring-atlas-border';
}

const dismissalReasons: Array<{ value: AdvisorDismissalReason; label: string }> = [
    { value: 'NotRelevant', label: 'Pas pertinente pour mon activité' },
    { value: 'AlreadyDone', label: 'Action déjà réalisée' },
    { value: 'NotNow', label: 'Pas maintenant' },
    { value: 'IncorrectContext', label: 'Contexte incorrect' },
    { value: 'Other', label: 'Autre raison' },
];

function RecommendationCard({ recommendation, primary = false, busy, submitting, onComplete, onDismiss }: {
    recommendation: AdvisorRecommendation;
    primary?: boolean;
    busy: boolean;
    submitting: boolean;
    onComplete: (recommendation: AdvisorRecommendation) => Promise<void>;
    onDismiss: (recommendation: AdvisorRecommendation, reason: AdvisorDismissalReason) => Promise<void>;
}) {
    const presentation = getRecommendationPresentation(recommendation.recommendation_key);
    const action = getRecommendationAction(recommendation.route_key);
    const [decision, setDecision] = useState<'complete' | 'dismiss' | null>(null);
    const [completionConfirmed, setCompletionConfirmed] = useState(false);
    const [dismissalReason, setDismissalReason] = useState<AdvisorDismissalReason | ''>('');
    const inputId = `advisor-complete-${recommendation.recommendation_id}`;

    return (
        <article className={primary
            ? 'overflow-hidden rounded-3xl bg-atlas-sidebar p-6 text-white shadow-sm sm:p-8'
            : 'rounded-2xl border border-atlas-border bg-atlas-card p-5 shadow-sm sm:p-6'}
        >
            <div className="flex flex-wrap items-start justify-between gap-5">
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${priorityClasses(recommendation.priority, primary)} ${primary ? '' : 'ring-1'}`}>
                            Priorité {formatPriority(recommendation.priority).toLocaleLowerCase('fr-FR')}
                        </span>
                        <span className={primary
                            ? 'rounded-full bg-white/10 px-2.5 py-1 text-xs font-medium text-white/75'
                            : 'rounded-full bg-slate-50 px-2.5 py-1 text-xs font-medium text-atlas-ink-muted ring-1 ring-atlas-border'}
                        >
                            {formatUrgency(recommendation.urgency)}
                        </span>
                    </div>
                    <p className={`mt-5 text-xs font-semibold uppercase tracking-[0.16em] ${primary ? 'text-white/55' : 'text-atlas-accent'}`}>
                        {primary ? 'Priorité recommandée' : 'Alternative'}
                    </p>
                    <h3 className={`mt-2 font-semibold tracking-tight ${primary ? 'text-2xl text-white sm:text-3xl' : 'text-xl text-atlas-ink'}`}>
                        {presentation.title}
                    </h3>
                    <p className={`mt-2 max-w-2xl text-sm leading-relaxed ${primary ? 'text-white/70' : 'text-atlas-ink-muted'}`}>
                        {presentation.description}
                    </p>

                    <dl className={`mt-6 grid gap-4 border-t pt-5 text-sm sm:grid-cols-3 ${primary ? 'border-white/10' : 'border-atlas-border'}`}>
                        <div>
                            <dt className={primary ? 'text-white/50' : 'text-atlas-ink-muted'}>Impact attendu</dt>
                            <dd className={`mt-1 font-semibold ${primary ? 'text-white' : 'text-atlas-ink'}`}>{formatImpact(recommendation.impact)}</dd>
                        </div>
                        <div>
                            <dt className={primary ? 'text-white/50' : 'text-atlas-ink-muted'}>Confiance</dt>
                            <dd className={`mt-1 font-semibold ${primary ? 'text-white' : 'text-atlas-ink'}`}>{formatConfidence(recommendation.confidence)}</dd>
                        </div>
                        <div>
                            <dt className={primary ? 'text-white/50' : 'text-atlas-ink-muted'}>Effort estimé</dt>
                            <dd className={`mt-1 font-semibold ${primary ? 'text-white' : 'text-atlas-ink'}`}>{formatEffort(recommendation.effort)}</dd>
                        </div>
                    </dl>
                </div>

                <div className="flex w-full flex-col gap-3 sm:w-auto sm:min-w-48 sm:items-end">
                    {action && (
                        <Link
                            to={action.to}
                            className={primary
                                ? 'inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-atlas-sidebar hover:bg-slate-100 sm:w-auto'
                                : 'inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-atlas-ink px-4 py-2.5 text-sm font-semibold text-white hover:bg-atlas-sidebar sm:w-auto'}
                        >
                            {action.label} <span className="ml-2" aria-hidden="true">→</span>
                        </Link>
                    )}
                    <p className={`text-xs ${primary ? 'text-white/50' : 'text-atlas-ink-muted'}`}>
                        Valable jusqu’au <time dateTime={recommendation.valid_until}>{formatValidityDate(recommendation.valid_until)}</time>
                    </p>
                </div>
            </div>

            <div className={`mt-6 border-t pt-5 ${primary ? 'border-white/10' : 'border-atlas-border'}`}>
                {decision === null && (
                    <div className="flex flex-wrap gap-3">
                        <button
                            type="button"
                            disabled={busy}
                            onClick={() => setDecision('complete')}
                            className={primary
                                ? 'rounded-xl border border-white/25 px-4 py-2.5 text-sm font-semibold text-white hover:bg-white/10 disabled:cursor-not-allowed disabled:opacity-50'
                                : 'rounded-xl border border-atlas-border bg-white px-4 py-2.5 text-sm font-semibold text-atlas-ink hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50'}
                        >
                            Marquer comme réalisée
                        </button>
                        <button
                            type="button"
                            disabled={busy}
                            onClick={() => setDecision('dismiss')}
                            className={`rounded-xl px-4 py-2.5 text-sm font-semibold disabled:cursor-not-allowed disabled:opacity-50 ${primary ? 'text-white/70 hover:bg-white/10 hover:text-white' : 'text-atlas-ink-muted hover:bg-slate-50 hover:text-atlas-ink'}`}
                        >
                            Écarter cette recommandation
                        </button>
                    </div>
                )}

                {decision === 'complete' && (
                    <fieldset disabled={busy} className="max-w-2xl">
                        <legend className={`text-sm font-semibold ${primary ? 'text-white' : 'text-atlas-ink'}`}>
                            Confirmer l’action réalisée
                        </legend>
                        <p className={`mt-1 text-sm ${primary ? 'text-white/65' : 'text-atlas-ink-muted'}`}>
                            Cette confirmation enregistre votre choix. Atlas ne déduit aucune modification dans le CRM ou la facturation.
                        </p>
                        <label htmlFor={inputId} className={`mt-4 flex cursor-pointer items-start gap-3 text-sm ${primary ? 'text-white/85' : 'text-atlas-ink'}`}>
                            <input
                                id={inputId}
                                type="checkbox"
                                checked={completionConfirmed}
                                onChange={(event) => setCompletionConfirmed(event.target.checked)}
                                className="mt-0.5 size-4 rounded border-slate-300 text-atlas-accent focus:ring-atlas-accent"
                            />
                            J’ai bien réalisé l’action principale proposée.
                        </label>
                        <div className="mt-4 flex flex-wrap gap-3">
                            <button
                                type="button"
                                disabled={!completionConfirmed || busy}
                                aria-busy={submitting}
                                onClick={() => void onComplete(recommendation)}
                                className="rounded-xl bg-atlas-accent px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {submitting ? 'Confirmation…' : 'Confirmer comme réalisée'}
                            </button>
                            <button type="button" onClick={() => setDecision(null)} className={`rounded-xl px-4 py-2.5 text-sm font-semibold ${primary ? 'text-white/70 hover:text-white' : 'text-atlas-ink-muted hover:text-atlas-ink'}`}>
                                Annuler
                            </button>
                        </div>
                    </fieldset>
                )}

                {decision === 'dismiss' && (
                    <fieldset disabled={busy} className="max-w-2xl">
                        <legend className={`text-sm font-semibold ${primary ? 'text-white' : 'text-atlas-ink'}`}>
                            Pourquoi l’écarter ?
                        </legend>
                        <p className={`mt-1 text-sm ${primary ? 'text-white/65' : 'text-atlas-ink-muted'}`}>
                            Le motif aide Advisor à respecter votre décision. Aucun commentaire libre n’est enregistré.
                        </p>
                        <label className={`mt-4 block text-sm font-medium ${primary ? 'text-white/85' : 'text-atlas-ink'}`}>
                            Motif
                            <select
                                value={dismissalReason}
                                onChange={(event) => setDismissalReason(event.target.value as AdvisorDismissalReason | '')}
                                className="mt-2 block min-h-11 w-full rounded-xl border border-atlas-border bg-white px-3 py-2 text-sm text-atlas-ink focus:border-atlas-accent focus:outline-none focus:ring-2 focus:ring-atlas-accent/20"
                            >
                                <option value="">Sélectionner un motif</option>
                                {dismissalReasons.map((reason) => (
                                    <option key={reason.value} value={reason.value}>{reason.label}</option>
                                ))}
                            </select>
                        </label>
                        <div className="mt-4 flex flex-wrap gap-3">
                            <button
                                type="button"
                                disabled={dismissalReason === '' || busy}
                                aria-busy={submitting}
                                onClick={() => dismissalReason !== '' && void onDismiss(recommendation, dismissalReason)}
                                className="rounded-xl bg-atlas-accent px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {submitting ? 'Enregistrement…' : 'Écarter la recommandation'}
                            </button>
                            <button type="button" onClick={() => setDecision(null)} className={`rounded-xl px-4 py-2.5 text-sm font-semibold ${primary ? 'text-white/70 hover:text-white' : 'text-atlas-ink-muted hover:text-atlas-ink'}`}>
                                Annuler
                            </button>
                        </div>
                    </fieldset>
                )}
            </div>
        </article>
    );
}

function EligibilityState({ eligibility }: { eligibility: AdvisorOverview['source_eligibility'] }) {
    const stale = eligibility === 'StaleAssessment';

    return (
        <EmptyState
            title={stale ? 'L’évaluation doit être actualisée' : 'Les données sont encore insuffisantes'}
            description={stale
                ? 'Advisor ne réutilise pas une ancienne évaluation. Publiez un nouveau snapshot avant de demander une priorité.'
                : 'Advisor attend une évaluation Santé disponible et suffisamment fiable avant de proposer une action.'}
            action={
                <div className="flex flex-wrap justify-center gap-3">
                    <Link to="/app/health" className="rounded-xl bg-atlas-accent px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90">
                        Comprendre l’évaluation
                    </Link>
                    {!stale && (
                        <Link to="/app/crm" className="rounded-xl border border-atlas-border bg-white px-4 py-2.5 text-sm font-semibold text-atlas-ink hover:bg-slate-50">
                            Ajouter des données CRM
                        </Link>
                    )}
                </div>
            }
        />
    );
}

export function AdvisorPage() {
    return (
        <RequireAuth>
            <AdvisorContent />
        </RequireAuth>
    );
}

function AdvisorContent() {
    const { session } = useAuth();
    const token = session!.token;
    const workspaceId = session!.workspaceId!;
    const [overview, setOverview] = useState<AdvisorOverview | null>(null);
    const [missing, setMissing] = useState(false);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [decisionError, setDecisionError] = useState<string | null>(null);
    const [feedback, setFeedback] = useState<string | null>(null);
    const [pendingRecommendationId, setPendingRecommendationId] = useState<string | null>(null);

    async function loadOverview() {
        setLoading(true);
        setError(null);
        setMissing(false);

        try {
            setOverview(await getAdvisorOverview(token, workspaceId));
        } catch (err) {
            if (isOverviewMissing(err)) {
                setOverview(null);
                setMissing(true);
            } else {
                setError(err instanceof Error ? err.message : 'Chargement des recommandations impossible');
            }
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => {
        void loadOverview();
    }, [token, workspaceId]);

    async function applyDecision(
        recommendation: AdvisorRecommendation,
        action: () => Promise<unknown>,
        successMessage: string,
    ) {
        setPendingRecommendationId(recommendation.recommendation_id);
        setFeedback(null);
        setDecisionError(null);
        setError(null);

        try {
            await action();
            await loadOverview();
            setFeedback(successMessage);
        } catch (err) {
            const staleDecision = err instanceof ApiClientError
                && (err.status === 409 || err.body.messages?.includes('Recommendation no longer active.'));

            if (staleDecision) {
                await loadOverview();
                setDecisionError('Cette recommandation a changé depuis son affichage. La liste a été actualisée ; vérifiez la nouvelle priorité.');
            } else {
                setDecisionError(err instanceof Error ? err.message : 'Enregistrement de la décision impossible');
            }
        } finally {
            setPendingRecommendationId(null);
        }
    }

    async function handleComplete(recommendation: AdvisorRecommendation) {
        await applyDecision(
            recommendation,
            () => completeAdvisorRecommendation(token, workspaceId, recommendation.recommendation_id, recommendation.revision),
            'Action marquée comme réalisée. La liste Advisor a été actualisée.',
        );
    }

    async function handleDismiss(recommendation: AdvisorRecommendation, reason: AdvisorDismissalReason) {
        await applyDecision(
            recommendation,
            () => dismissAdvisorRecommendation(token, workspaceId, recommendation.recommendation_id, reason, recommendation.revision),
            'Recommandation écartée. La liste Advisor a été actualisée.',
        );
    }

    const eligibleWithoutRecommendation = overview?.source_eligibility === 'Eligible'
        && overview.primary_recommendation === null;

    return (
        <div className="atlas-page max-w-6xl">
            <PageHeader
                eyebrow="Advisor"
                title="Vos prochaines actions"
                description="Des priorités expliquées à partir de la dernière évaluation Santé, sans exécuter d’action à votre place."
                actions={overview ? (
                    <p className="text-xs text-atlas-ink-muted">
                        Mis à jour le <time dateTime={overview.updated_at}>{formatAdvisorDate(overview.updated_at)}</time>
                    </p>
                ) : undefined}
            />

            {loading && <div className="mt-8"><PageSkeleton rows={3} variant="cards" /></div>}

            {feedback && !loading && (
                <div role="status" className="mt-8 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                    {feedback}
                </div>
            )}

            {decisionError && !loading && (
                <div className="mt-8"><ErrorBanner message={decisionError} /></div>
            )}

            {error && (
                <div className="mt-8">
                    <ErrorBanner message={error} />
                    <button type="button" onClick={() => void loadOverview()} className="mt-2 text-sm font-semibold text-atlas-accent hover:underline">
                        Réessayer
                    </button>
                </div>
            )}

            {!loading && !error && missing && (
                <div className="mt-8">
                    <EmptyState
                        title="Aucune évaluation Advisor pour le moment"
                        description="Une première priorité pourra être proposée après la consolidation des données et l’évaluation Santé."
                        action={
                            <Link to="/app/health" className="rounded-xl bg-atlas-accent px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90">
                                Voir la santé de l’activité
                            </Link>
                        }
                    />
                </div>
            )}

            {!loading && !error && overview && overview.source_eligibility !== 'Eligible' && (
                <div className="mt-8"><EligibilityState eligibility={overview.source_eligibility} /></div>
            )}

            {!loading && !error && eligibleWithoutRecommendation && (
                <div className="mt-8">
                    <EmptyState
                        title="Aucune action prioritaire identifiée"
                        description="La dernière évaluation est exploitable, mais aucune règle Advisor ne justifie une recommandation active."
                        action={
                            <Link to="/app/health" className="rounded-xl border border-atlas-border bg-white px-4 py-2.5 text-sm font-semibold text-atlas-ink hover:bg-slate-50">
                                Consulter les preuves
                            </Link>
                        }
                    />
                </div>
            )}

            {!loading && !error && overview?.source_eligibility === 'Eligible' && overview.primary_recommendation && (
                <div className="mt-8 space-y-8">
                    <RecommendationCard
                        recommendation={overview.primary_recommendation}
                        primary
                        busy={pendingRecommendationId !== null}
                        submitting={pendingRecommendationId === overview.primary_recommendation.recommendation_id}
                        onComplete={handleComplete}
                        onDismiss={handleDismiss}
                    />

                    {overview.alternative_recommendations.length > 0 && (
                        <section aria-labelledby="advisor-alternatives-title">
                            <h3 id="advisor-alternatives-title" className="text-xl font-semibold text-atlas-ink">Autres actions utiles</h3>
                            <p className="mt-1 text-sm text-atlas-ink-muted">Advisor limite cette vue aux trois recommandations les plus pertinentes.</p>
                            <div className="mt-4 grid gap-4 lg:grid-cols-2">
                                {overview.alternative_recommendations.map((recommendation) => (
                                    <RecommendationCard
                                        key={recommendation.recommendation_id}
                                        recommendation={recommendation}
                                        busy={pendingRecommendationId !== null}
                                        submitting={pendingRecommendationId === recommendation.recommendation_id}
                                        onComplete={handleComplete}
                                        onDismiss={handleDismiss}
                                    />
                                ))}
                            </div>
                        </section>
                    )}

                    <aside className="rounded-2xl border border-atlas-border bg-atlas-card p-5 text-sm leading-relaxed text-atlas-ink-muted">
                        <p>
                            Advisor ordonne les actions à partir des preuves Business Health. Ouvrir une action vous conduit vers son module propriétaire, qui recharge les données et vérifie vos droits avant toute modification.
                        </p>
                        <Link to="/app/health" className="mt-3 inline-flex font-semibold text-atlas-accent hover:underline">
                            Examiner les preuves de l’évaluation →
                        </Link>
                    </aside>
                </div>
            )}
        </div>
    );
}
