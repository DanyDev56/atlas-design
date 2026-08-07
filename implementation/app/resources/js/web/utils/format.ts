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
        Active: 'Actif',
        Archived: 'Archivé',
    };
    return labels[status] ?? status;
}
