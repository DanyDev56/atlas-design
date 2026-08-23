import type { ReactNode } from 'react';

interface PageHeaderProps {
    eyebrow?: string;
    title: string;
    description?: string;
    actions?: ReactNode;
}

export function PageHeader({ eyebrow, title, description, actions }: PageHeaderProps) {
    return (
        <header className="atlas-page-header">
            <div className="min-w-0">
                {eyebrow && <p className="atlas-kicker">{eyebrow}</p>}
                <h2 className="mt-1.5 text-[2rem] font-semibold leading-tight tracking-[-0.035em] text-atlas-ink sm:text-[2.35rem]">
                    {title}
                </h2>
                {description && (
                    <p className="mt-2.5 max-w-2xl text-[0.9375rem] leading-6 text-atlas-ink-muted">
                        {description}
                    </p>
                )}
            </div>
            {actions && <div className="flex shrink-0 flex-wrap items-center gap-2.5">{actions}</div>}
        </header>
    );
}
