import type { ReactNode } from 'react';
import type { DataState } from '@/types/api';

interface WidgetCardProps {
    title: string;
    subtitle?: string;
    dataState: DataState;
    observedAt?: string | null;
    children: ReactNode;
    accent?: 'default' | 'priority' | 'health';
}

const stateLabels: Record<DataState, string> = {
    Data: 'À jour',
    NoData: 'Pas encore de données',
    InsufficientData: 'Données insuffisantes',
    Unavailable: 'Indisponible',
};

const accentRing: Record<NonNullable<WidgetCardProps['accent']>, string> = {
    default: 'border-atlas-border',
    priority: 'border-atlas-warm/25 bg-gradient-to-br from-white via-white to-amber-50/60',
    health: 'border-atlas-accent/25 bg-gradient-to-br from-white to-emerald-50/30',
};

export function WidgetCard({
    title,
    subtitle,
    dataState,
    observedAt,
    children,
    accent = 'default',
}: WidgetCardProps) {
    return (
        <article className={`group relative overflow-hidden rounded-[1.25rem] border bg-atlas-card p-5 shadow-sm sm:p-6 ${accentRing[accent]}`}>
            <span className={`absolute inset-x-0 top-0 h-0.5 ${accent === 'priority' ? 'bg-atlas-warm/70' : accent === 'health' ? 'bg-atlas-accent/65' : 'bg-transparent'}`} aria-hidden="true" />
            <header className="mb-4 flex items-start justify-between gap-4">
                <div>
                    <h3 className="text-base font-semibold tracking-[-0.01em] text-atlas-ink">{title}</h3>
                    {subtitle && <p className="mt-0.5 text-sm text-atlas-ink-muted">{subtitle}</p>}
                </div>
                <span
                    className={`shrink-0 rounded-full border px-2.5 py-1 text-[11px] font-semibold ${
                        dataState === 'Data'
                            ? 'border-teal-200/80 bg-atlas-accent-soft text-atlas-accent'
                            : 'border-slate-200 bg-slate-100 text-atlas-ink-muted'
                    }`}
                >
                    {stateLabels[dataState]}
                </span>
            </header>

            <div className="min-h-[5rem]">{children}</div>

            {observedAt && dataState === 'Data' && (
                <p className="mt-4 text-xs text-atlas-ink-muted">
                    Mis à jour{' '}
                    <time dateTime={observedAt}>{new Date(observedAt).toLocaleString('fr-FR')}</time>
                </p>
            )}
        </article>
    );
}

export function EmptyWidgetMessage({ children }: { children: ReactNode }) {
    return (
        <div className="flex h-full flex-col items-start justify-center gap-3 rounded-xl border border-dashed border-atlas-border bg-atlas-surface/70 px-4 py-6 text-sm leading-relaxed text-atlas-ink-muted">
            {children}
        </div>
    );
}
