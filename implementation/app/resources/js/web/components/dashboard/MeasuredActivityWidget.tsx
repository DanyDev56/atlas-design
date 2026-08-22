import { Link } from 'react-router-dom';
import { WidgetCard, EmptyWidgetMessage } from '@/components/dashboard/WidgetCard';
import type { AnalyticsMetricObservation, DashboardWidget, MeasuredActivityPayload } from '@/types/api';
import { formatMoney } from '@/utils/format';

const metricCopy: Record<string, { label: string; window: string }> = {
    'analytics.pipeline.open-amount': { label: 'Pipeline ouvert', window: 'À date' },
    'analytics.quotes.pending-amount': { label: 'Devis en attente', window: 'À date' },
    'analytics.billing.collected-amount': { label: 'Encaissé', window: '30 jours' },
    'analytics.receivables.outstanding-amount': { label: 'Créances', window: 'À date' },
    'analytics.receivables.overdue-amount': { label: 'Retard', window: 'À date' },
    'analytics.receivables.overdue-count': { label: 'Factures en retard', window: 'À date' },
};

function formatObservation(observation: AnalyticsMetricObservation): string {
    if (observation.value_status !== 'Available') {
        return 'Pas de donnée';
    }

    if (typeof observation.count === 'number') {
        return new Intl.NumberFormat('fr-FR').format(observation.count);
    }

    const amounts = observation.values_by_currency ?? {};
    const currencies = Object.keys(amounts);

    if (currencies.length === 0) {
        return 'Pas de donnée';
    }

    return currencies
        .map((currency) => formatMoney(amounts[currency] ?? 0, currency))
        .join(' · ');
}

function freshnessLabel(status: string): string {
    if (status === 'Current') {
        return 'Fraîcheur à jour';
    }

    if (status === 'Lagging') {
        return 'Données en retard';
    }

    return status;
}

export function MeasuredActivityWidget({ widget }: { widget: DashboardWidget<MeasuredActivityPayload> }) {
    const overview = widget.payload;
    const metricKeys = overview ? Object.keys(overview.metrics) : [];

    return (
        <WidgetCard
            title="Activité mesurée"
            subtitle="Métriques Analytics publiées, sans recalcul local"
            dataState={widget.data_state}
            observedAt={widget.observed_at}
        >
            {widget.data_state === 'Data' && overview ? (
                <div>
                    <p className="text-xs text-atlas-ink-muted">
                        {freshnessLabel(overview.freshness_status)}
                        {overview.completeness_status === 'Complete' ? ' · Couverture complète' : ''}
                    </p>
                    <dl className="mt-4 grid gap-3 sm:grid-cols-2">
                        {metricKeys.map((metricKey) => {
                            const copy = metricCopy[metricKey] ?? { label: metricKey, window: overview.metrics[metricKey].window_kind };
                            const observation = overview.metrics[metricKey];

                            return (
                                <div key={metricKey} className="rounded-xl bg-atlas-surface px-3 py-3">
                                    <dt className="text-xs font-medium text-atlas-ink-muted">
                                        {copy.label}
                                        <span className="ml-1 font-normal">({copy.window})</span>
                                    </dt>
                                    <dd className="mt-1 text-sm font-semibold tabular-nums text-atlas-ink">
                                        {formatObservation(observation)}
                                    </dd>
                                </div>
                            );
                        })}
                    </dl>
                    <Link to="/app/health" className="mt-5 inline-flex text-sm font-semibold text-atlas-accent hover:underline">
                        Ouvrir l’explication Santé / Analytics <span className="ml-1" aria-hidden="true">→</span>
                    </Link>
                </div>
            ) : (
                <EmptyWidgetMessage>
                    <span>
                        {widget.data_state === 'Unavailable'
                            ? 'Les métriques Analytics sont temporairement indisponibles.'
                            : 'Aucune génération Analytics n’est encore publiée. Les totaux n’apparaissent qu’après un snapshot, jamais à 0 % inventé.'}
                    </span>
                    {widget.data_state !== 'Unavailable' && (
                        <Link to="/app/crm" className="font-semibold text-atlas-accent hover:underline">
                            Enregistrer une première activité →
                        </Link>
                    )}
                </EmptyWidgetMessage>
            )}
        </WidgetCard>
    );
}
