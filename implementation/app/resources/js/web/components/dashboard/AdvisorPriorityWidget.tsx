import { Link } from 'react-router-dom';
import { WidgetCard, EmptyWidgetMessage } from '@/components/dashboard/WidgetCard';
import type { DashboardWidget, AdvisorOverview } from '@/types/api';
import { getRecommendationAction, getRecommendationPresentation } from '@/utils/advisor';
import { formatImpact, formatPriority, formatUrgency } from '@/utils/format';

export function AdvisorPriorityWidget({ widget }: { widget: DashboardWidget<AdvisorOverview> }) {
    const rec = widget.payload?.primary_recommendation;
    const action = getRecommendationAction(rec?.route_key);
    const presentation = rec ? getRecommendationPresentation(rec.recommendation_key) : null;

    return (
        <WidgetCard
            title="Priorité du jour"
            subtitle="L’action qui mérite votre attention en premier"
            dataState={widget.data_state}
            observedAt={widget.observed_at}
            accent="priority"
        >
            {widget.data_state === 'Data' && rec ? (
                <div className="grid gap-6 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                    <div>
                        <div className="flex flex-wrap gap-2">
                            <span className="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-900">
                                Priorité {formatPriority(rec.priority).toLocaleLowerCase('fr-FR')}
                            </span>
                            {rec.urgency && (
                                <span className="rounded-full bg-white px-2.5 py-1 text-xs font-medium text-atlas-ink-muted ring-1 ring-atlas-border">
                                    {formatUrgency(rec.urgency)}
                                </span>
                            )}
                            {rec.impact && (
                                <span className="rounded-full bg-white px-2.5 py-1 text-xs font-medium text-atlas-ink-muted ring-1 ring-atlas-border">
                                    {formatImpact(rec.impact)}
                                </span>
                            )}
                        </div>
                        <p className="mt-4 text-xl font-semibold tracking-tight text-atlas-ink sm:text-2xl">
                            {presentation?.title}
                        </p>
                        <p className="mt-2 max-w-2xl text-sm leading-relaxed text-atlas-ink-muted">
                            {presentation?.description}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2 sm:justify-end">
                        {action && (
                            <Link
                                to={action.to}
                                className="inline-flex min-h-11 items-center justify-center rounded-xl bg-atlas-ink px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-atlas-sidebar"
                            >
                                {action.label}
                                <span aria-hidden="true" className="ml-2">→</span>
                            </Link>
                        )}
                        <Link
                            to="/app/advisor"
                            className="inline-flex min-h-11 items-center justify-center rounded-xl border border-atlas-border bg-white px-4 py-2.5 text-sm font-semibold text-atlas-ink transition-colors hover:bg-slate-50"
                        >
                            Voir Advisor
                        </Link>
                    </div>
                </div>
            ) : (
                <EmptyWidgetMessage>
                    <span>
                        {widget.data_state === 'Unavailable'
                            ? 'La priorité du jour est temporairement indisponible. Les autres indicateurs restent consultables.'
                            : 'Aucune priorité proposée pour l’instant. Ajoutez un client et une opportunité pour démarrer.'}
                    </span>
                    {widget.data_state !== 'Unavailable' && (
                        <Link to="/app/advisor" className="font-semibold text-atlas-accent hover:underline">
                            Comprendre l’absence de priorité →
                        </Link>
                    )}
                </EmptyWidgetMessage>
            )}
        </WidgetCard>
    );
}
