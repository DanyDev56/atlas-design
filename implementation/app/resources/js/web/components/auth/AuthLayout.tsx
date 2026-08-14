import type { ReactNode } from 'react';

export function AuthLayout({ children, title, subtitle }: { children: ReactNode; title: string; subtitle: string }) {
    return (
        <div className="flex min-h-screen">
            <div className="hidden w-1/2 flex-col justify-between bg-atlas-sidebar p-12 text-white lg:flex">
                <div>
                    <p className="text-xs font-semibold uppercase tracking-[0.25em] text-white/50">Atlas</p>
                    <h1 className="mt-4 max-w-md text-4xl font-semibold leading-tight tracking-tight">
                        Comprenez votre activité. Décidez avec clarté.
                    </h1>
                    <p className="mt-6 max-w-sm text-base leading-relaxed text-white/70">
                        CRM, facturation et pilotage réunis — sans tableaux de bord inventés ni certitudes
                        artificielles.
                    </p>
                </div>
                <p className="text-sm text-white/40">Beta interne — Palier 4 UI démo</p>
            </div>

            <div className="flex w-full flex-col justify-center px-6 py-12 lg:w-1/2 lg:px-16">
                <div className="mx-auto w-full max-w-md">
                    <p className="text-xs font-semibold uppercase tracking-[0.2em] text-atlas-accent lg:hidden">Atlas</p>
                    <h2 className="mt-2 text-2xl font-semibold text-atlas-ink">{title}</h2>
                    <p className="mt-2 text-sm text-atlas-ink-muted">{subtitle}</p>
                    <div className="mt-8">{children}</div>
                </div>
            </div>
        </div>
    );
}

export function FormField({
    label,
    hint,
    children,
}: {
    label: string;
    hint?: string;
    children: ReactNode;
}) {
    return (
        <label className="block">
            <span className="mb-1.5 block text-sm font-medium text-atlas-ink">{label}</span>
            {children}
            {hint && <span className="mt-1.5 block text-xs leading-relaxed text-atlas-ink-muted">{hint}</span>}
        </label>
    );
}

export const inputClassName =
    'w-full rounded-xl border border-atlas-border bg-white px-4 py-2.5 text-sm text-atlas-ink outline-none transition-shadow focus:border-atlas-accent focus:ring-2 focus:ring-atlas-accent/20';

export function SubmitButton({
    loading,
    disabled = false,
    loadingLabel = 'Patientez…',
    children,
}: {
    loading: boolean;
    disabled?: boolean;
    loadingLabel?: string;
    children: ReactNode;
}) {
    return (
        <button
            type="submit"
            disabled={loading || disabled}
            aria-busy={loading}
            className="w-full rounded-xl bg-atlas-accent px-4 py-3 text-sm font-semibold text-white transition-opacity hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
        >
            {loading ? loadingLabel : children}
        </button>
    );
}

export function ErrorBanner({ message }: { message: string | null }) {
    if (!message) return null;
    return (
        <div role="alert" className="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            {message}
        </div>
    );
}

export function SuccessBanner({ message }: { message: string | null }) {
    if (!message) return null;

    return (
        <div
            role="status"
            className="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
        >
            {message}
        </div>
    );
}
