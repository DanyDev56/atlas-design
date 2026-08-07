import { WidgetCard, EmptyWidgetMessage } from '@/components/dashboard/WidgetCard';
import type { DashboardWidget, AdvisorOverview } from '@/types/api';

export function AdvisorPriorityWidget({ widget }: { widget: DashboardWidget<AdvisorOverview> }) {
    const rec = widget.payload?.primary_recommendation;

    return (
        <WidgetCard
            title="Priorité du jour"
            subtitle="Recommendation Advisor"
            dataState={widget.data_state}
            observedAt={widget.observed_at}
            accent="priority"
        >
            {widget.data_state === 'Data' && rec ? (
                <div>
                    <p className="text-xs font-semibold uppercase tracking-wide text-atlas-warm">{rec.priority}</p>
                    <p className="mt-2 text-lg font-semibold text-atlas-ink">{rec.recommendation_key}</p>
                    {rec.rule_key && (
                        <p className="mt-2 text-sm leading-relaxed text-atlas-ink-muted">{rec.rule_key}</p>
                    )}
                </div>
            ) : (
                <EmptyWidgetMessage>
                    {widget.data_state === 'NoData'
                        ? 'Aucune priorité proposée pour l’instant. Enregistrez des faits CRM et Billing pour alimenter l’Advisor.'
                        : 'La priorité Advisor n’est pas disponible pour le moment.'}
                </EmptyWidgetMessage>
            )}
        </WidgetCard>
    );
}
