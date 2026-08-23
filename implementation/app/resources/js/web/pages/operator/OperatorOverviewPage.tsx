import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { ApiClientError } from '@/api/client';
import { fetchOperatorOverview, type OperatorOverview, type OperatorOverviewCard } from '@/api/operator';
import { OperatorFrame } from '@/components/operator/OperatorFrame';
import { Icon } from '@/components/ui/Icon';
import { useOperatorAuth } from '@/hooks/useOperatorAuth';

const statusLabels = { Available: 'Collecté', NotCollected: 'Non collecté', Unavailable: 'Indisponible' };

function statusClass(card: OperatorOverviewCard): string {
    if (card.status === 'Unavailable' || card.tone === 'Critical') return 'border-red-200 bg-red-50 text-red-700';
    if (card.tone === 'Warning') return 'border-amber-200 bg-amber-50 text-amber-700';
    if (card.status === 'Available') return 'border-emerald-200 bg-emerald-50 text-emerald-700';
    return 'border-atlas-border bg-atlas-surface text-atlas-ink-muted';
}

function formatFreshness(seconds: number): string {
    if (seconds < 60) return `${seconds} s`;
    if (seconds < 3600) return `${Math.round(seconds / 60)} min`;
    return `${Math.round(seconds / 3600)} h`;
}

export function OperatorOverviewPage() {
    const { session } = useOperatorAuth();
    const [overview, setOverview] = useState<OperatorOverview | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [loading, setLoading] = useState(true);

    const load = useCallback(async () => {
        if (!session?.token) return;
        setLoading(true);
        setError(null);
        try {
            setOverview(await fetchOperatorOverview(session.token));
        } catch (caught) {
            setError(caught instanceof ApiClientError ? caught.message : 'Le tableau opérateur n’a pas pu être chargé.');
        } finally {
            setLoading(false);
        }
    }, [session?.token]);

    useEffect(() => {
        const previousTitle = document.title;
        document.title = 'Vue d’ensemble opérateur — Atlas';
        void load();
        return () => { document.title = previousTitle; };
    }, [load]);

    return (
        <OperatorFrame>
            <main className="mx-auto max-w-[90rem] px-5 py-10 sm:px-8 lg:px-10 lg:py-14">
                <div className="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p className="atlas-kicker">Plan de contrôle</p>
                        <h1 className="mt-3 text-4xl font-semibold tracking-[-0.045em] sm:text-5xl">Vue d’ensemble</h1>
                        <p className="mt-4 max-w-2xl text-base leading-7 text-atlas-ink-muted">
                            Une lecture opérationnelle traçable. Une absence d’instrumentation reste visible et n’est jamais transformée en faux zéro.
                        </p>
                    </div>
                    <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-900">
                        <div className="flex items-center gap-2 font-semibold"><span className="size-2 rounded-full bg-emerald-500" />Mode lecture seule</div>
                        <p className="mt-1 text-xs text-emerald-800/70">Actions opérateur désactivées.</p>
                    </div>
                </div>

                <section className="mt-10 rounded-[1.5rem] bg-atlas-sidebar p-6 text-white shadow-[0_18px_45px_rgb(16_28_26/0.16)] sm:p-8">
                    <div className="grid gap-7 sm:grid-cols-2 lg:grid-cols-4">
                        <div><p className="text-xs uppercase tracking-[.16em] text-white/45">Audience</p><p className="mt-2 text-xl font-semibold">Operator</p></div>
                        <div><p className="text-xs uppercase tracking-[.16em] text-white/45">Permissions</p><p className="mt-2 text-xl font-semibold">{session?.permissions.length ?? 0}</p></div>
                        <div><p className="text-xs uppercase tracking-[.16em] text-white/45">Points d’attention</p><p className="mt-2 text-xl font-semibold">{overview?.attention_count ?? '—'}</p></div>
                        <div><p className="text-xs uppercase tracking-[.16em] text-white/45">Dernière lecture</p><p className="mt-2 text-xl font-semibold">{overview ? new Intl.DateTimeFormat('fr-FR', { hour: '2-digit', minute: '2-digit' }).format(new Date(overview.generated_at)) : '—'}</p></div>
                    </div>
                </section>

                <section className="mt-10">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div><h2 className="text-xl font-semibold">État des opérations</h2><p className="mt-1 text-sm text-atlas-ink-muted">Source, fraîcheur et périmètre accompagnent chaque indicateur.</p></div>
                        <button type="button" onClick={() => void load()} disabled={loading} className="min-h-10 rounded-xl border border-atlas-border bg-white px-4 text-sm font-semibold disabled:opacity-50">{loading ? 'Actualisation…' : 'Actualiser'}</button>
                    </div>
                    {error && <div role="alert" className="mt-5 flex items-center justify-between gap-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><span>{error}</span><button type="button" className="font-semibold underline" onClick={() => void load()}>Réessayer</button></div>}
                    {loading && !overview && <div className="mt-5 rounded-2xl border border-atlas-border bg-white p-8 text-sm text-atlas-ink-muted">Lecture des sources opérationnelles…</div>}
                    {overview && (
                        <div className="mt-5 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                            {overview.cards.map((card) => {
                                const canOpen = card.href && card.detail_permission && session?.permissions.includes(card.detail_permission);
                                return (
                                    <article key={card.key} className="flex min-h-64 flex-col rounded-2xl border border-atlas-border bg-white p-6 shadow-sm">
                                        <div className="flex items-start justify-between gap-4"><h3 className="text-lg font-semibold">{card.label}</h3><span className={`shrink-0 rounded-full border px-2.5 py-1 text-[11px] font-semibold ${statusClass(card)}`}>{statusLabels[card.status]}</span></div>
                                        <p className="mt-2 text-sm leading-6 text-atlas-ink-muted">{card.description}</p>
                                        {card.values.length > 0 && <dl className="mt-5 grid grid-cols-3 gap-3">{card.values.map((value) => <div key={value.key}><dt className="text-xs text-atlas-ink-muted">{value.label}</dt><dd className="mt-1 text-xl font-semibold tabular-nums">{value.value}</dd></div>)}</dl>}
                                        <p className="mt-5 text-sm text-atlas-ink-muted">{card.context}</p>
                                        <div className="mt-auto border-t border-atlas-border pt-4">
                                            <p className="break-all text-[11px] text-atlas-ink-muted/75">Source : {card.source}</p>
                                            <p className="mt-1 text-[11px] text-atlas-ink-muted/75">
                                                {card.measured_at
                                                    ? `Calculé à ${new Intl.DateTimeFormat('fr-FR', { hour: '2-digit', minute: '2-digit' }).format(new Date(card.measured_at))} · cible ${formatFreshness(card.freshness.target_seconds)}`
                                                    : `Fraîcheur cible : ${formatFreshness(card.freshness.target_seconds)}`}
                                            </p>
                                            {canOpen ? <Link to={card.href!} className="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-atlas-accent">Ouvrir le registre <Icon name="arrow-right" className="size-4" /></Link> : card.href && <p className="mt-3 text-xs font-medium text-atlas-ink-muted">Grant de détail requis</p>}
                                        </div>
                                    </article>
                                );
                            })}
                        </div>
                    )}
                </section>
            </main>
        </OperatorFrame>
    );
}
