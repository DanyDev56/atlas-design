import type { ReactNode } from 'react';

interface EmptyStateProps {
    title: string;
    description: string;
    action?: ReactNode;
}

export function EmptyState({ title, description, action }: EmptyStateProps) {
    return (
        <div className="rounded-2xl border border-dashed border-atlas-border bg-atlas-card px-6 py-12 text-center">
            <p className="text-base font-medium text-atlas-ink">{title}</p>
            <p className="mx-auto mt-2 max-w-md text-sm leading-relaxed text-atlas-ink-muted">{description}</p>
            {action && <div className="mt-6 flex justify-center">{action}</div>}
        </div>
    );
}
