export interface RecommendationPresentation {
    title: string;
    description: string;
}

export interface RecommendationAction {
    to: string;
    label: string;
}

const recommendationPresentations: Record<string, RecommendationPresentation> = {
    'advisor.collect-overdue-invoices': {
        title: 'Relancer les factures en retard',
        description: 'Commencez par les encours les plus anciens pour accélérer les rentrées de trésorerie.',
    },
    'advisor.reduce-client-concentration': {
        title: 'Réduire la dépendance à un seul client',
        description: 'Développez une nouvelle opportunité pour mieux répartir vos encaissements.',
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

const recommendationActions: Record<string, RecommendationAction> = {
    OverdueInvoices: { to: '/app/billing', label: 'Voir les factures' },
    OutstandingInvoices: { to: '/app/billing', label: 'Voir les encours' },
    RecentInvoices: { to: '/app/billing', label: 'Voir la facturation' },
    NewOpportunity: { to: '/app/crm', label: 'Créer une opportunité' },
    OpportunityPipeline: { to: '/app/crm', label: 'Voir le pipeline' },
};

export function getRecommendationPresentation(recommendationKey: string): RecommendationPresentation {
    return recommendationPresentations[recommendationKey] ?? {
        title: 'Une action mérite votre attention',
        description: 'Consultez le module concerné pour choisir la prochaine action utile.',
    };
}

export function getRecommendationAction(routeKey: string | null | undefined): RecommendationAction | null {
    return routeKey ? (recommendationActions[routeKey] ?? null) : null;
}
