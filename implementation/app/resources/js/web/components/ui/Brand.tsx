export function Brand({ inverse = false, compact = false }: { inverse?: boolean; compact?: boolean }) {
    return (
        <div className="flex items-center gap-3">
            <span
                className={`grid size-10 shrink-0 place-items-center rounded-[13px] shadow-sm ${
                    inverse ? 'bg-white text-atlas-sidebar' : 'bg-atlas-sidebar text-white'
                }`}
            >
                <svg aria-hidden="true" viewBox="0 0 32 32" className="size-6" fill="none">
                    <path d="M6.5 24 14.7 7.8a1.45 1.45 0 0 1 2.6 0L25.5 24" stroke="currentColor" strokeWidth="2.6" strokeLinecap="round" strokeLinejoin="round" />
                    <path d="M10.3 19h11.4" stroke="currentColor" strokeWidth="2.6" strokeLinecap="round" />
                    <circle cx="16" cy="24" r="2" fill="#35b394" />
                </svg>
            </span>
            {!compact && (
                <span>
                    <span className={`block text-[10px] font-semibold uppercase tracking-[0.24em] ${inverse ? 'text-white/45' : 'text-atlas-ink-muted'}`}>
                        Atlas
                    </span>
                    <span className={`block text-base font-semibold tracking-tight ${inverse ? 'text-white' : 'text-atlas-ink'}`}>
                        Pilotage
                    </span>
                </span>
            )}
        </div>
    );
}
