import { Link } from 'react-router-dom';
import { WidgetCard, EmptyWidgetMessage } from '@/components/dashboard/WidgetCard';
import type { DashboardWidget, AdvisorOverview } from '@/types/api';
import { formatImpact, formatPriority, formatUrgency } from '@/utils/format';

interface RecommendationCopy {
    title: string;
    description: string;
}

const recommendationCopy: Record<string, RecommendationCopy> = {
    'advisor.collect-overdue-invoices': {
        title: 'Relancer les factures en retard',
        description: 'Commencez par les encours les plus anciens pour accélérer les rentrées de trésorerie.',
    },
    'advisor.reduce-client-concentration': {
        title: 'Réduire la dépendance à un seul client',
        description: 'Développez une nouvelle opportunité pour mieux répartir votre chiffre d’affaires.',
    },
    'advisor.rebuild-commercial-pipeline': {
        title: 'Relancer votre prospection',
        description: 'Ajoutez une opportunité qualifiée pour redonner de la profondeur au pipeline.',
    },
    'advisor.restore-billing-momentum': {
        title: 'Remettre la facturation en mouvement',
        description: 'Transformez les affaires engagées en devis puis en factures sans attendre.',
    },
    'advisor.address-primary-attention': {
        title: 'Agir sur le point le plus fragile',
        description: 'Concentrez votre prochaine action sur la zone qui pèse le plus sur la santé de l’activité.',
    },
};

function recommendationAction(rec: NonNullable<AdvisorOverview['primary_recommendation']>) {
    if (rec.action_module === 'Billing') {
        return { href: '/app/billing', label: 'Voir la facturation' };
    }

    return { href: '/app/crm', label: 'Ouvrir le CRM' };
}

export function AdvisorPriorityWidget({ widget }: { widget: DashboardWidget<AdvisorOverview> }) {
    const rec = widget.payload?.primary_recommendation;
    const action = rec ? recommendationAction(rec) : null;

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
                            {recommendationCopy[rec.recommendation_key]?.title ?? 'Une action mérite votre attention'}
                        </p>
                        <p className="mt-2 max-w-2xl text-sm leading-relaxed text-atlas-ink-muted">
                            {recommendationCopy[rec.recommendation_key]?.description ??
                                'Consultez le module concerné pour choisir la prochaine action utile.'}
                        </p>
                    </div>
                    {action?.href.startsWith('/app') ? (
                        <Link
                            to={action.href}
                            className="inline-flex min-h-11 items-center justify-center rounded-xl bg-atlas-ink px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-atlas-sidebar"
                        >
                            {action.label}
                            <span aria-hidden="true" className="ml-2">→</span>
                        </Link>
                    ) : (
                        <a
                            href={action?.href}
                            className="inline-flex min-h-11 items-center justify-center rounded-xl bg-atlas-ink px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-atlas-sidebar"
                        >
                            {action?.label}
                            <span aria-hidden="true" className="ml-2">→</span>
                        </a>
                    )}
                </div>
            ) : (
                <EmptyWidgetMessage>
                    <span>
                        {widget.data_state === 'Unavailable'
                            ? 'La priorité du jour est temporairement indisponible. Les autres indicateurs restent consultables.'
                            : 'Aucune priorité proposée pour l’instant. Ajoutez un client et une opportunité pour démarrer.'}
                    </span>
                    {widget.data_state !== 'Unavailable' && (
                        <Link to="/app/crm" className="font-semibold text-atlas-accent hover:underline">
                            Commencer dans le CRM →
                        </Link>
                    )}
                </EmptyWidgetMessage>
            )}
        </WidgetCard>
    );
}
