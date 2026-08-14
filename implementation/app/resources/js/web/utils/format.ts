export function formatMoney(cents: number, currency: string): string {
    return new Intl.NumberFormat('fr-FR', { style: 'currency', currency }).format(cents / 100);
}

export function formatStatus(status: string): string {
    const labels: Record<string, string> = {
        Open: 'Ouverte',
        Qualified: 'Qualifiée',
        Won: 'Gagnée',
        Lost: 'Perdue',
        Draft: 'Brouillon',
        Sent: 'Envoyé',
        Accepted: 'Accepté',
        Issued: 'Émise',
        Unpaid: 'À encaisser',
        PartiallyPaid: 'Partiellement réglée',
        Paid: 'Réglée',
        Active: 'Actif',
        Archived: 'Archivé',
    };
    return labels[status] ?? status;
}

export function formatPriority(priority: string): string {
    const labels: Record<string, string> = {
        Critical: 'Urgente',
        High: 'Haute',
        Medium: 'À planifier',
        Low: 'À surveiller',
    };

    return labels[priority] ?? priority;
}

export function formatUrgency(urgency: string): string {
    const labels: Record<string, string> = {
        Immediate: 'À faire maintenant',
        Today: "À faire aujourd'hui",
        ThisWeek: 'À faire cette semaine',
        NoDeadline: 'Sans échéance',
    };

    return labels[urgency] ?? urgency;
}

export function formatImpact(impact: string): string {
    const labels: Record<string, string> = {
        Major: 'Impact majeur',
        Significant: 'Impact significatif',
        Moderate: 'Impact modéré',
        Minor: 'Impact limité',
    };

    return labels[impact] ?? impact;
}

export function formatHealthBand(band: string): string {
    const labels: Record<string, string> = {
        Strong: 'Solide',
        Stable: 'Stable',
        Watch: 'À surveiller',
        AtRisk: 'Fragile',
        Critical: 'Critique',
    };

    return labels[band] ?? band;
}

export function formatReliability(reliability: string): string {
    const labels: Record<string, string> = {
        Reliable: 'fiable',
        Limited: 'limitée',
        Insufficient: 'insuffisante',
    };

    return labels[reliability] ?? reliability.toLocaleLowerCase('fr-FR');
}
