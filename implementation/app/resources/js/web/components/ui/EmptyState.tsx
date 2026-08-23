import type { ReactNode } from 'react';
import { Icon } from '@/components/ui/Icon';

interface EmptyStateProps {
    title: string;
    description: string;
    action?: ReactNode;
}

export function EmptyState({ title, description, action }: EmptyStateProps) {
    return (
        <div className="rounded-[1.25rem] border border-dashed border-atlas-border bg-white/65 px-6 py-12 text-center shadow-[inset_0_1px_0_rgb(255_255_255/0.8)]">
            <span className="mx-auto grid size-11 place-items-center rounded-2xl bg-atlas-accent-soft text-atlas-accent">
                <Icon name="advisor" className="size-5" />
            </span>
            <h3 className="mt-4 text-base font-semibold text-atlas-ink">{title}</h3>
            <p className="mx-auto mt-2 max-w-md text-sm leading-6 text-atlas-ink-muted">{description}</p>
            {action && <div className="mt-6 flex justify-center">{action}</div>}
        </div>
    );
}
