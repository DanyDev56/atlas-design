import { WidgetCard, EmptyWidgetMessage } from '@/components/dashboard/WidgetCard';
import type { DashboardWidget, BusinessHealthCurrent } from '@/types/api';

export function BusinessHealthWidget({ widget }: { widget: DashboardWidget<BusinessHealthCurrent> }) {
    const health = widget.payload;

    return (
        <WidgetCard
            title="Santé de l'activité"
            subtitle="Business Health"
            dataState={widget.data_state}
            observedAt={widget.observed_at}
            accent="health"
        >
            {widget.data_state === 'Data' && health ? (
                <div className="flex items-end gap-6">
                    {health.score != null && (
                        <p className="text-5xl font-bold tabular-nums tracking-tight text-atlas-accent">
                            {health.score}
                        </p>
                    )}
                    <div className="pb-1">
                        {health.band && (
                            <p className="text-sm font-semibold text-atlas-ink">{health.band}</p>
                        )}
                        {health.reliability && (
                            <p className="text-sm text-atlas-ink-muted">Fiabilité {health.reliability}</p>
                        )}
                    </div>
                </div>
            ) : (
                <EmptyWidgetMessage>
                    {widget.data_state === 'InsufficientData'
                        ? 'Données insuffisantes pour une évaluation fiable. Continuez à alimenter CRM et Billing.'
                        : 'Pas encore d’évaluation de santé. Publiez un snapshot Analytics pour démarrer.'}
                </EmptyWidgetMessage>
            )}
        </WidgetCard>
    );
}
