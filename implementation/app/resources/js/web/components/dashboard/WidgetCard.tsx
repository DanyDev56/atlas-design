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
    priority: 'border-atlas-warm/30',
    health: 'border-atlas-accent/30',
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
        <article className={`rounded-2xl border bg-atlas-card p-6 shadow-sm ${accentRing[accent]}`}>
            <header className="mb-4 flex items-start justify-between gap-4">
                <div>
                    <h3 className="text-base font-semibold text-atlas-ink">{title}</h3>
                    {subtitle && <p className="mt-0.5 text-sm text-atlas-ink-muted">{subtitle}</p>}
                </div>
                <span
                    className={`shrink-0 rounded-full px-2.5 py-1 text-xs font-medium ${
                        dataState === 'Data'
                            ? 'bg-atlas-accent-soft text-atlas-accent'
                            : 'bg-slate-100 text-atlas-ink-muted'
                    }`}
                >
                    {stateLabels[dataState]}
                </span>
            </header>

            <div className="min-h-[5rem]">{children}</div>

            {observedAt && dataState === 'Data' && (
                <p className="mt-4 text-xs text-atlas-ink-muted">
                    Observé {new Date(observedAt).toLocaleString('fr-FR')}
                </p>
            )}
        </article>
    );
}

export function EmptyWidgetMessage({ children }: { children: ReactNode }) {
    return (
        <div className="flex h-full flex-col items-start justify-center gap-2 rounded-xl bg-atlas-surface px-4 py-6">
            <p className="text-sm leading-relaxed text-atlas-ink-muted">{children}</p>
        </div>
    );
}
