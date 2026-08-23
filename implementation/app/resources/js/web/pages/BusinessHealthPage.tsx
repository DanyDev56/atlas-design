import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { publishAnalyticsSnapshot } from '@/api/analytics';
import { getCurrentBusinessHealth } from '@/api/businessHealth';
import { ApiClientError } from '@/api/client';
import { ErrorBanner } from '@/components/auth/AuthLayout';
import { RequireAuth } from '@/components/layout/RequireAuth';
import { EmptyState } from '@/components/ui/EmptyState';
import { PageHeader } from '@/components/ui/PageHeader';
import { PageSkeleton } from '@/components/ui/PageSkeleton';
import { useAuth } from '@/hooks/useAuth';
import type {
    BusinessHealthAssessment,
    BusinessHealthComponent,
    BusinessHealthFactor,
    BusinessHealthRisk,
} from '@/types/api';
import { formatHealthBand, formatReliability } from '@/utils/format';

interface FactorPresentation {
    label: string;
    description: string;
    action: { to: string; label: string };
}

const factorPresentation: Record<string, FactorPresentation> = {
    CommercialMomentum: {
        label: 'Dynamique commerciale',
        description: 'Acceptation des devis et évolution du pipeline.',
        action: { to: '/app/crm', label: 'Voir le CRM' },
    },
    BillingMomentum: {
        label: 'Dynamique de facturation',
        description: 'Évolution des montants facturés et encaissés.',
        action: { to: '/app/billing', label: 'Voir la facturation' },
    },
    ReceivablesDiscipline: {
        label: 'Discipline d’encaissement',
        description: 'Exposition aux retards et régularité des règlements.',
        action: { to: '/app/billing', label: 'Voir les factures' },
    },
    ClientDiversification: {
        label: 'Diversification client',
        description: 'Répartition des encaissements entre vos clients.',
        action: { to: '/app/crm', label: 'Voir les clients' },
    },
};

const componentLabels: Record<string, string> = {
    quote_acceptance: 'Acceptation des devis',
    pipeline_evolution: 'Évolution du pipeline',
    net_invoiced_evolution: 'Évolution du montant facturé',
    collected_evolution: 'Évolution du montant encaissé',
    overdue_load: 'Poids des factures en retard',
    on_time_payment: 'Règlements dans les délais',
    top_client_share: 'Concentration du premier client',
};

const riskLabels: Record<string, string> = {
    OverdueExposureRisk: 'Exposition aux factures en retard',
    ClientConcentrationRisk: 'Concentration des encaissements client',
    CommercialMomentumRisk: 'Ralentissement commercial',
    BillingMomentumRisk: 'Ralentissement de la facturation',
};

const unavailableReasons: Record<string, string> = {
    NoData: 'Données absentes',
    SampleBelowMinimum: 'Échantillon encore trop faible',
    InconsistentReceivables: 'Données d’encaissement incohérentes',
    StaleSnapshot: 'Projection des données en retard',
};

const assessmentPollAttempts = 8;
const assessmentPollDelayMs = 750;

function wait(delayMs: number): Promise<void> {
    return new Promise((resolve) => window.setTimeout(resolve, delayMs));
}

function formatAssessmentDate(value: string): string {
    const date = new Date(value);

    if (Number.isNaN(date.getTime())) return 'Date indisponible';

    return new Intl.DateTimeFormat('fr-FR', {
        dateStyle: 'long',
        timeStyle: 'short',
    }).format(date);
}

function formatTrend(trend: string): string {
    const labels: Record<string, string> = {
        Improving: 'En amélioration',
        Stable: 'Stable',
        Declining: 'En recul',
        Unknown: 'Pas encore comparable',
    };

    return labels[trend] ?? trend;
}

function formatSeverity(severity: BusinessHealthRisk['severity']): string {
    const labels: Record<BusinessHealthRisk['severity'], string> = {
        Low: 'Faible',
        Medium: 'Modérée',
        High: 'Élevée',
        Critical: 'Critique',
    };

    return labels[severity];
}

function severityClasses(severity: BusinessHealthRisk['severity']): string {
    if (severity === 'Critical') return 'border-red-200 bg-red-50 text-red-900';
    if (severity === 'High') return 'border-amber-200 bg-amber-50 text-amber-950';
    if (severity === 'Medium') return 'border-yellow-200 bg-yellow-50 text-yellow-950';

    return 'border-atlas-border bg-slate-50 text-atlas-ink';
}

function isAssessmentMissing(error: unknown): boolean {
    return error instanceof ApiClientError
        && (error.status === 404 || error.body.messages?.includes('Assessment not found.'));
}

function formatSnapshotError(error: unknown): string {
    if (error instanceof ApiClientError) {
        if (error.body.messages?.includes('Insufficient data.')) {
            return 'Il manque encore des données CRM ou Facturation pour publier une nouvelle analyse.';
        }

        if (error.body.messages?.includes('Stale data.')) {
            return 'La projection des données est en retard. Attendez son traitement par le worker avant de réessayer.';
        }

        if (error.body.messages?.includes('Source data processing incomplete.')) {
            return 'Des modifications CRM ou Facturation sont encore en cours de traitement. Attendez quelques instants avant de réessayer.';
        }

        if (error.status === 403) {
            return 'Vous n’avez pas l’autorisation d’actualiser cette analyse.';
        }
    }

    return error instanceof Error ? error.message : 'Actualisation de l’analyse impossible';
}

function FactorCard({ factorKey, factor }: { factorKey: string; factor: BusinessHealthFactor }) {
    const presentation = factorPresentation[factorKey] ?? {
        label: factorKey,
        description: 'Facteur de l’évaluation actuelle.',
        action: { to: '/app', label: 'Voir le dashboard' },
    };

    return (
        <article className="rounded-2xl border border-atlas-border bg-atlas-card p-5 shadow-sm">
            <div className="flex min-h-28 flex-col">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h3 className="font-semibold text-atlas-ink">{presentation.label}</h3>
                        <p className="mt-1 text-sm leading-relaxed text-atlas-ink-muted">{presentation.description}</p>
                    </div>
                    {factor.status === 'Available' && factor.score != null ? (
                        <span className="text-2xl font-bold tabular-nums text-atlas-accent">
                            {factor.score}<span className="ml-0.5 text-xs font-medium text-atlas-ink-muted">/100</span>
                        </span>
                    ) : (
                        <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-atlas-ink-muted">
                            À compléter
                        </span>
                    )}
                </div>
                <div className="mt-auto pt-4">
                    <div className="flex items-center justify-between gap-3 text-xs text-atlas-ink-muted">
                        <span>Couverture des données</span>
                        <span className="font-semibold tabular-nums">{factor.coverage_percent}%</span>
                    </div>
                    <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100" aria-hidden="true">
                        <div
                            className="h-full rounded-full bg-atlas-accent/70"
                            style={{ width: `${Math.max(0, Math.min(100, factor.coverage_percent))}%` }}
                        />
                    </div>
                    <Link to={presentation.action.to} className="mt-4 inline-flex text-sm font-semibold text-atlas-accent hover:underline">
                        {presentation.action.label} <span className="ml-1" aria-hidden="true">→</span>
                    </Link>
                </div>
            </div>
        </article>
    );
}

function ComponentRow({ componentKey, component }: { componentKey: string; component: BusinessHealthComponent }) {
    const label = componentLabels[componentKey] ?? componentKey;
    const available = component.status === 'Available' && component.score != null;

    return (
        <li className="flex flex-wrap items-center justify-between gap-3 border-b border-atlas-border py-3 last:border-0">
            <span className="text-sm font-medium text-atlas-ink">{label}</span>
            {available ? (
                <span className="text-sm font-semibold tabular-nums text-atlas-ink">{component.score}/100</span>
            ) : (
                <span className="text-xs font-medium text-atlas-ink-muted">
                    {component.reason ? (unavailableReasons[component.reason] ?? component.reason) : 'Indisponible'}
                </span>
            )}
        </li>
    );
}

export function BusinessHealthPage() {
    return (
        <RequireAuth>
            <BusinessHealthContent />
        </RequireAuth>
    );
}

function BusinessHealthContent() {
    const { session } = useAuth();
    const token = session!.token;
    const workspaceId = session!.workspaceId!;
    const [assessment, setAssessment] = useState<BusinessHealthAssessment | null>(null);
    const [missing, setMissing] = useState(false);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [refreshing, setRefreshing] = useState(false);
    const [refreshError, setRefreshError] = useState<string | null>(null);
    const [refreshMessage, setRefreshMessage] = useState<string | null>(null);
    const [pendingSnapshotId, setPendingSnapshotId] = useState<string | null>(null);

    async function loadAssessment() {
        setLoading(true);
        setError(null);
        setMissing(false);

        try {
            setAssessment(await getCurrentBusinessHealth(token, workspaceId));
        } catch (err) {
            if (isAssessmentMissing(err)) {
                setAssessment(null);
                setMissing(true);
            } else {
                setError(err instanceof Error ? err.message : 'Chargement de l’évaluation impossible');
            }
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => {
        void loadAssessment();
    }, [token, workspaceId]);

    async function findPublishedAssessment(snapshotId: string): Promise<BusinessHealthAssessment | null> {
        for (let attempt = 0; attempt < assessmentPollAttempts; attempt += 1) {
            await wait(assessmentPollDelayMs);

            try {
                const current = await getCurrentBusinessHealth(token, workspaceId);
                if (current.analytics_snapshot_id === snapshotId) return current;
            } catch {
                // The projection may not exist yet while the worker consumes the snapshot.
            }
        }

        return null;
    }

    function applyPublishedAssessment(current: BusinessHealthAssessment) {
        setAssessment(current);
        setMissing(false);
        setPendingSnapshotId(null);
        setRefreshMessage('Analyse actualisée. La nouvelle évaluation est affichée.');
    }

    async function handleRefreshAnalysis() {
        setRefreshing(true);
        setRefreshError(null);
        setRefreshMessage('Publication du snapshot Analytics…');

        try {
            const publication = await publishAnalyticsSnapshot(token, workspaceId);

            if (publication.freshness_status === 'Lagging') {
                setPendingSnapshotId(null);
                setRefreshMessage('Aucune donnée plus récente n’est disponible. La dernière analyse valide reste affichée.');

                return;
            }

            setRefreshMessage('Snapshot publié. Le worker prépare la nouvelle évaluation…');

            const current = await findPublishedAssessment(publication.analytics_snapshot_id);
            if (current) {
                applyPublishedAssessment(current);
            } else {
                setPendingSnapshotId(publication.analytics_snapshot_id);
                setRefreshMessage('Le snapshot est publié. L’évaluation est encore en cours de traitement par le worker.');
            }
        } catch (err) {
            setRefreshMessage(null);
            setRefreshError(formatSnapshotError(err));
        } finally {
            setRefreshing(false);
        }
    }

    async function handleCheckPendingAssessment() {
        if (!pendingSnapshotId) return;

        setRefreshing(true);
        setRefreshError(null);

        try {
            const current = await getCurrentBusinessHealth(token, workspaceId);
            if (current.analytics_snapshot_id === pendingSnapshotId) {
                applyPublishedAssessment(current);
            } else {
                setRefreshMessage('Le worker n’a pas encore terminé cette évaluation.');
            }
        } catch (err) {
            setRefreshError(err instanceof Error ? err.message : 'Vérification de l’évaluation impossible');
        } finally {
            setRefreshing(false);
        }
    }

    const primaryFactor = assessment?.primary_attention
        ? factorPresentation[assessment.primary_attention.factor_key]
        : null;

    return (
        <div className="atlas-page max-w-6xl">
            <PageHeader
                eyebrow="Pilotage"
                title="Santé de l’activité"
                description="Une lecture explicable de vos données commerciales et de facturation récentes."
                actions={<div className="flex flex-col items-start gap-3 sm:items-end">
                    {assessment && (
                        <p className="text-xs text-atlas-ink-muted">
                            Évaluation du <time dateTime={assessment.assessed_at}>{formatAssessmentDate(assessment.assessed_at)}</time>
                        </p>
                    )}
                    <button
                        type="button"
                        disabled={refreshing || pendingSnapshotId !== null}
                        onClick={() => void handleRefreshAnalysis()}
                        className="inline-flex min-h-11 items-center justify-center rounded-xl border border-atlas-border bg-white px-4 py-2.5 text-sm font-semibold text-atlas-ink shadow-sm hover:bg-slate-50 disabled:cursor-wait disabled:opacity-60"
                    >
                        {refreshing
                            ? 'Actualisation…'
                            : pendingSnapshotId
                              ? 'En attente du worker'
                              : 'Actualiser l’analyse'}
                    </button>
                </div>}
            />

            {refreshError && (
                <div className="mt-6">
                    <ErrorBanner message={refreshError} />
                </div>
            )}

            {refreshMessage && !refreshError && (
                <div
                    role="status"
                    aria-live="polite"
                    className="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-atlas-border bg-atlas-card px-4 py-3 text-sm text-atlas-ink"
                >
                    <span>{refreshMessage}</span>
                    {pendingSnapshotId && !refreshing && (
                        <button
                            type="button"
                            onClick={() => void handleCheckPendingAssessment()}
                            className="font-semibold text-atlas-accent hover:underline"
                        >
                            Vérifier maintenant
                        </button>
                    )}
                </div>
            )}

            {loading && <div className="mt-8"><PageSkeleton rows={4} variant="cards" /></div>}

            {error && (
                <div className="mt-8">
                    <ErrorBanner message={error} />
                    <button
                        type="button"
                        onClick={() => void loadAssessment()}
                        className="mt-2 text-sm font-semibold text-atlas-accent hover:underline"
                    >
                        Réessayer
                    </button>
                </div>
            )}

            {!loading && !error && missing && (
                <div className="mt-8">
                    <EmptyState
                        title="Votre première évaluation se prépare"
                        description="Ajoutez de l’activité commerciale et de facturation. La santé de l’activité apparaîtra après la prochaine consolidation des données."
                        action={
                            <div className="flex flex-wrap justify-center gap-3">
                                <Link to="/app/crm" className="rounded-xl bg-atlas-accent px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90">
                                    Ajouter une opportunité
                                </Link>
                                <Link to="/app/billing" className="rounded-xl border border-atlas-border bg-white px-4 py-2.5 text-sm font-semibold text-atlas-ink hover:bg-slate-50">
                                    Voir la facturation
                                </Link>
                            </div>
                        }
                    />
                </div>
            )}

            {!loading && !error && assessment && (
                <div className="mt-8 space-y-8">
                    <section aria-labelledby="health-summary-title" className="overflow-hidden rounded-3xl bg-atlas-sidebar text-white shadow-sm">
                        <div className="grid gap-8 p-6 sm:p-8 lg:grid-cols-[minmax(0,1fr)_18rem] lg:items-center">
                            <div>
                                <p id="health-summary-title" className="text-sm font-semibold text-white/65">Évaluation actuelle</p>
                                {assessment.overall_score != null ? (
                                    <div className="mt-3 flex flex-wrap items-end gap-x-5 gap-y-2">
                                        <p className="text-6xl font-bold tabular-nums tracking-tight">
                                            {assessment.overall_score}<span className="ml-1 text-lg font-medium text-white/55">/100</span>
                                        </p>
                                        <p className="pb-1 text-lg font-semibold text-white/90">
                                            {assessment.health_band ? formatHealthBand(assessment.health_band) : 'Évaluation disponible'}
                                        </p>
                                    </div>
                                ) : (
                                    <div className="mt-3">
                                        <p className="text-2xl font-semibold">Données encore insuffisantes</p>
                                        <p className="mt-2 max-w-xl text-sm leading-relaxed text-white/65">
                                            Aucun score n’est affiché tant que la couverture minimale n’est pas atteinte.
                                        </p>
                                    </div>
                                )}
                                <div className="mt-6 flex flex-wrap gap-2 text-xs font-semibold">
                                    <span className="rounded-full bg-white/10 px-3 py-1.5">
                                        Fiabilité {formatReliability(assessment.assessment_reliability)}
                                    </span>
                                    <span className="rounded-full bg-white/10 px-3 py-1.5">
                                        Tendance : {formatTrend(assessment.health_trend)}
                                    </span>
                                </div>
                            </div>
                            <div className="rounded-2xl bg-white/8 p-5">
                                <div className="flex items-center justify-between gap-3">
                                    <span className="text-sm text-white/65">Couverture globale</span>
                                    <span className="text-xl font-bold tabular-nums">{assessment.global_coverage_percent}%</span>
                                </div>
                                <div className="mt-3 h-2 overflow-hidden rounded-full bg-white/15" aria-hidden="true">
                                    <div
                                        className="h-full rounded-full bg-white"
                                        style={{ width: `${Math.max(0, Math.min(100, assessment.global_coverage_percent))}%` }}
                                    />
                                </div>
                                <p className="mt-4 text-xs leading-relaxed text-white/55">
                                    Ce score décrit les données disponibles. Il ne constitue ni un diagnostic financier ni une prévision.
                                </p>
                            </div>
                        </div>
                    </section>

                    {assessment.primary_attention && primaryFactor && (
                        <section aria-labelledby="primary-attention-title" className="rounded-2xl border border-amber-200 bg-amber-50 p-5 sm:p-6">
                            <p className="text-xs font-semibold uppercase tracking-[0.16em] text-amber-800">Point d’attention principal</p>
                            <div className="mt-2 flex flex-wrap items-end justify-between gap-4">
                                <div>
                                    <h3 id="primary-attention-title" className="text-lg font-semibold text-amber-950">{primaryFactor.label}</h3>
                                    <p className="mt-1 text-sm leading-relaxed text-amber-900/75">{primaryFactor.description}</p>
                                </div>
                                <Link to={primaryFactor.action.to} className="inline-flex min-h-11 items-center rounded-xl bg-amber-950 px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90">
                                    {primaryFactor.action.label} <span className="ml-2" aria-hidden="true">→</span>
                                </Link>
                            </div>
                        </section>
                    )}

                    <section aria-labelledby="health-factors-title">
                        <div>
                            <h3 id="health-factors-title" className="text-xl font-semibold text-atlas-ink">Les quatre facteurs</h3>
                            <p className="mt-1 text-sm text-atlas-ink-muted">Chaque note et sa couverture proviennent de l’évaluation publiée.</p>
                        </div>
                        <div className="mt-4 grid gap-4 md:grid-cols-2">
                            {Object.entries(assessment.factors).map(([factorKey, factor]) => (
                                <FactorCard key={factorKey} factorKey={factorKey} factor={factor} />
                            ))}
                        </div>
                    </section>

                    {assessment.risks.length > 0 && (
                        <section aria-labelledby="health-risks-title">
                            <h3 id="health-risks-title" className="text-xl font-semibold text-atlas-ink">Risques observés</h3>
                            <p className="mt-1 text-sm text-atlas-ink-muted">Ces signaux reposent uniquement sur les preuves disponibles dans l’évaluation.</p>
                            <ul className="mt-4 grid gap-3 md:grid-cols-2">
                                {assessment.risks.map((risk) => (
                                    <li key={risk.risk_key} className={`rounded-2xl border p-4 ${severityClasses(risk.severity)}`}>
                                        <p className="font-semibold">{riskLabels[risk.risk_key] ?? risk.risk_key}</p>
                                        <p className="mt-1 text-xs font-medium">Niveau {formatSeverity(risk.severity).toLocaleLowerCase('fr-FR')}</p>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    )}

                    <section aria-labelledby="health-evidence-title" className="rounded-2xl border border-atlas-border bg-atlas-card p-5 shadow-sm sm:p-6">
                        <div className="flex flex-wrap items-end justify-between gap-3">
                            <div>
                                <h3 id="health-evidence-title" className="text-xl font-semibold text-atlas-ink">Détail des preuves</h3>
                                <p className="mt-1 text-sm text-atlas-ink-muted">Les données absentes restent explicitement signalées et ne valent jamais zéro.</p>
                            </div>
                            <span className="text-xs text-atlas-ink-muted">Politique {assessment.health_policy_version}</span>
                        </div>
                        <ul className="mt-4">
                            {Object.entries(assessment.components).map(([componentKey, component]) => (
                                <ComponentRow key={componentKey} componentKey={componentKey} component={component} />
                            ))}
                        </ul>
                    </section>
                </div>
            )}
        </div>
    );
}
