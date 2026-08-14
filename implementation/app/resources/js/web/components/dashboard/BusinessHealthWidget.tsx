import { Link } from 'react-router-dom';
import { WidgetCard, EmptyWidgetMessage } from '@/components/dashboard/WidgetCard';
import type { DashboardWidget, BusinessHealthCurrent } from '@/types/api';
import { formatHealthBand, formatReliability } from '@/utils/format';

export function BusinessHealthWidget({ widget }: { widget: DashboardWidget<BusinessHealthCurrent> }) {
    const health = widget.payload;
    const score = health?.overall_score;

    return (
        <WidgetCard
            title="Santé de l'activité"
            subtitle="Une lecture synthétique de vos données récentes"
            dataState={widget.data_state}
            observedAt={widget.observed_at}
            accent="health"
        >
            {widget.data_state === 'Data' && health ? (
                <div>
                    <div className="flex items-end justify-between gap-6">
                        <div className="flex items-end gap-3">
                            {score != null && (
                                <p className="text-5xl font-bold tabular-nums tracking-tight text-atlas-accent">
                                    {score}<span className="ml-1 text-base font-medium text-atlas-ink-muted">/100</span>
                                </p>
                            )}
                        </div>
                        <div className="pb-1 text-right">
                            {health.health_band && (
                                <p className="text-sm font-semibold text-atlas-ink">
                                    {formatHealthBand(health.health_band)}
                                </p>
                            )}
                            {health.assessment_reliability && (
                                <p className="mt-0.5 text-xs text-atlas-ink-muted">
                                    Fiabilité {formatReliability(health.assessment_reliability)}
                                </p>
                            )}
                        </div>
                    </div>
                    {score != null && (
                        <div className="mt-5 h-2 overflow-hidden rounded-full bg-atlas-accent-soft" aria-hidden="true">
                            <div
                                className="h-full rounded-full bg-atlas-accent"
                                style={{ width: `${Math.max(0, Math.min(100, score))}%` }}
                            />
                        </div>
                    )}
                </div>
            ) : (
                <EmptyWidgetMessage>
                    <span>
                        {widget.data_state === 'Unavailable'
                            ? 'La santé de l’activité est temporairement indisponible.'
                            : widget.data_state === 'InsufficientData'
                              ? 'Il manque encore des données pour produire une évaluation fiable.'
                              : 'Votre première évaluation apparaîtra après quelques données commerciales et de facturation.'}
                    </span>
                    {widget.data_state !== 'Unavailable' && (
                        <Link to="/app/crm" className="font-semibold text-atlas-accent hover:underline">
                            Ajouter des données CRM →
                        </Link>
                    )}
                </EmptyWidgetMessage>
            )}
        </WidgetCard>
    );
}
