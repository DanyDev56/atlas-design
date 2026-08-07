import type { ReactNode } from 'react';
import { Link } from 'react-router-dom';

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
                    <p className="mt-8 text-center text-sm text-atlas-ink-muted">
                        <Link to="/app/login" className="text-atlas-accent hover:underline">Connexion</Link>
                        {' · '}
                        <Link to="/app/register" className="text-atlas-accent hover:underline">Inscription</Link>
                    </p>
                </div>
            </div>
        </div>
    );
}

export function FormField({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    return (
        <label className="block">
            <span className="mb-1.5 block text-sm font-medium text-atlas-ink">{label}</span>
            {children}
        </label>
    );
}

export const inputClassName =
    'w-full rounded-xl border border-atlas-border bg-white px-4 py-2.5 text-sm text-atlas-ink outline-none transition-shadow focus:border-atlas-accent focus:ring-2 focus:ring-atlas-accent/20';

export function SubmitButton({ loading, children }: { loading: boolean; children: ReactNode }) {
    return (
        <button
            type="submit"
            disabled={loading}
            className="w-full rounded-xl bg-atlas-accent px-4 py-3 text-sm font-semibold text-white transition-opacity hover:opacity-90 disabled:opacity-60"
        >
            {loading ? 'Patientez…' : children}
        </button>
    );
}

export function ErrorBanner({ message }: { message: string | null }) {
    if (!message) return null;
    return (
        <div className="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            {message}
        </div>
    );
}
