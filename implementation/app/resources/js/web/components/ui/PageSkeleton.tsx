interface PageSkeletonProps {
    rows?: number;
    variant?: 'cards' | 'list';
}

export function PageSkeleton({ rows = 3, variant = 'list' }: PageSkeletonProps) {
    if (variant === 'cards') {
        return (
            <div className="grid gap-6 md:grid-cols-2">
                {Array.from({ length: rows }, (_, i) => (
                    <div key={i} className="h-48 animate-pulse rounded-2xl bg-white shadow-sm" />
                ))}
            </div>
        );
    }

    return (
        <div className="space-y-3">
            {Array.from({ length: rows }, (_, i) => (
                <div key={i} className="h-16 animate-pulse rounded-xl bg-white shadow-sm" />
            ))}
        </div>
    );
}
