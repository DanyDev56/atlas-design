interface PageSkeletonProps {
    rows?: number;
    variant?: 'cards' | 'list';
}

export function PageSkeleton({ rows = 3, variant = 'list' }: PageSkeletonProps) {
    if (variant === 'cards') {
        return (
            <div role="status" aria-live="polite" className="grid gap-6 md:grid-cols-2">
                <span className="sr-only">Chargement des informations…</span>
                {Array.from({ length: rows }, (_, i) => (
                    <div aria-hidden="true" key={i} className="atlas-shimmer h-48 rounded-[1.25rem] border border-white shadow-sm" />
                ))}
            </div>
        );
    }

    return (
        <div role="status" aria-live="polite" className="space-y-3">
            <span className="sr-only">Chargement des informations…</span>
            {Array.from({ length: rows }, (_, i) => (
                <div aria-hidden="true" key={i} className="atlas-shimmer h-16 rounded-xl border border-white shadow-sm" />
            ))}
        </div>
    );
}
